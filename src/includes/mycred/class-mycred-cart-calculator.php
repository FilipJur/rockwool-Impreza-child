<?php
/**
 * MyCred Cart Calculator Class
 *
 * Handles all calculations related to product costs and cart totals
 * in the myCred points system.
 *
 * @package impreza-child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class MyCred_Cart_Calculator
{

	/**
	 * Get effective point cost for a product
	 *
	 * @param WC_Product|int $product_object Product object or ID
	 * @return float|null Product cost in points, null if invalid
	 */
	public function get_product_point_cost($product_object)
	{
		if (!$product_object instanceof WC_Product) {
			// Try to get product object if ID was passed
			$product_object = wc_get_product($product_object);
		}

		if (!$product_object) {
			return null; // Product not found
		}

		$price = $product_object->get_price();

		// Return float or null if price is not valid
		return ($price !== '' && is_numeric($price)) ? floatval($price) : null;
	}

	/**
	 * Get total point value of current cart
	 *
	 * @return float Total points value of cart contents
	 */
	public function get_cart_points_total()
	{
		// Ensure WooCommerce and cart are available
		if (!function_exists('WC') || !WC()->cart) {
			return 0.0;
		}

		if (WC()->cart->is_empty()) {
			return 0.0;
		}

		$cart_total_points = 0.0;

		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			// Get product object from cart item
			$_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

			// Get point cost for this product
			$product_cost_points = $this->get_product_point_cost($_product);

			$quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 0;

			if (!is_null($product_cost_points) && $quantity > 0) {
				$cart_total_points += ($product_cost_points * $quantity);
			}
		}

		return (float) $cart_total_points;
	}

	/**
	 * Get total point value of current cart excluding a specific product
	 *
	 * @param WC_Product $exclude_product Product to exclude from calculation
	 * @return float Total points value of cart contents excluding specified product
	 */
	public function get_cart_points_total_excluding_product($exclude_product)
	{
		// Ensure WooCommerce and cart are available
		if (!function_exists('WC') || !WC()->cart) {
			return 0.0;
		}

		if (WC()->cart->is_empty()) {
			return 0.0;
		}

		$cart_total_points = 0.0;
		$exclude_product_id = $exclude_product->get_id();

		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			// Get product object from cart item
			$_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

			// Skip if this is the product we want to exclude
			if ($_product->get_id() === $exclude_product_id) {
				continue;
			}

			// Get point cost for this product
			$product_cost_points = $this->get_product_point_cost($_product);

			$quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 0;

			if (!is_null($product_cost_points) && $quantity > 0) {
				$cart_total_points += ($product_cost_points * $quantity);
			}
		}

		return (float) $cart_total_points;
	}

	/**
	 * Calculate potential total cost if product is added to cart
	 *
	 * @param WC_Product $product Product to potentially add
	 * @param int $quantity Quantity to add (default: 1)
	 * @return float Potential total cost including cart + new product
	 */
	public function get_potential_total_cost($product, $quantity = 1)
	{
		$current_cart_total = $this->get_cart_points_total_excluding_product($product);
		$product_cost = $this->get_product_point_cost($product);

		if (is_null($product_cost) || $product_cost < 0) {
			return $current_cart_total; // Don't add invalid costs
		}

		return $current_cart_total + ($product_cost * $quantity);
	}

	/**
	 * Get user's current point balance
	 *
	 * @param int|null $user_id User ID (defaults to current user)
	 * @return float User's point balance
	 */
	public function get_user_balance($user_id = null)
	{
		if (is_null($user_id)) {
			$user_id = get_current_user_id();
		}

		if (!$user_id) {
			return 0.0;
		}

		$manager = MyCred_Manager::get_instance();
		$point_type_key = $manager->get_woo_point_type();

		if (empty($point_type_key)) {
			return 0.0;
		}

		$point_type_object = mycred($point_type_key);

		if (!$point_type_object || !is_object($point_type_object) || !method_exists($point_type_object, 'get_users_balance')) {
			return 0.0;
		}

		return (float) $point_type_object->get_users_balance($user_id);
	}

	/**
	 * Check if user can afford a product considering current cart
	 *
	 * @param WC_Product $product Product to check
	 * @param int|null $user_id User ID (defaults to current user)
	 * @param int $quantity Quantity to check (default: 1)
	 * @return bool True if user can afford the product
	 */
	public function can_user_afford_product($product, $user_id = null, $quantity = 1)
	{
		$user_balance = $this->get_user_balance($user_id);
		$potential_total = $this->get_potential_total_cost($product, $quantity);

		return $potential_total <= $user_balance;
	}
}
