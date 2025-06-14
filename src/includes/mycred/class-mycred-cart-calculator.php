<?php

declare(strict_types=1);

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
	public function get_product_point_cost(WC_Product|int $product_object): ?float
	{
		if (!$product_object instanceof WC_Product) {
			$product_object = wc_get_product($product_object);
		}

		if (!$product_object) {
			mycred_debug('Product not found', $product_object, 'calculator', 'warning');
			return null;
		}

		$price = $product_object->get_price();
		$cost = ($price !== '' && is_numeric($price)) ? (float)$price : null;
		
		// Only log if cost calculation failed
		if ($cost === null && $price !== '') {
			mycred_debug('Invalid product price detected', [
				'product_id' => $product_object->get_id(),
				'price' => $price
			], 'calculator', 'warning');
		}
		
		return $cost;
	}

	/**
	 * Get total point value of current cart
	 *
	 * @param int|null $exclude_product_id Optional product ID to exclude
	 * @return float Total points value of cart contents
	 */
	public function get_cart_points_total(?int $exclude_product_id = null): float
	{
		if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
			return 0.0;
		}

		$cart_total_points = 0.0;

		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			$_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
			
			// Skip excluded product
			if ($exclude_product_id && $_product->get_id() === $exclude_product_id) {
				continue;
			}

			$product_cost_points = $this->get_product_point_cost($_product);
			$quantity = $cart_item['quantity'] ?? 0;

			if ($product_cost_points !== null && $quantity > 0) {
				$cart_total_points += ($product_cost_points * $quantity);
			}
		}

		// Only log cart totals if there are items or debugging is verbose
		if ($cart_total_points > 0) {
			mycred_debug('Cart total calculated', [
				'total' => $cart_total_points,
				'excluded_product' => $exclude_product_id
			], 'calculator', 'verbose');
		}

		return $cart_total_points;
	}

	/**
	 * Get total point value of current cart excluding a specific product
	 *
	 * @param WC_Product $exclude_product Product to exclude from calculation
	 * @return float Total points value of cart contents excluding specified product
	 */
	public function get_cart_points_total_excluding_product(WC_Product $exclude_product): float
	{
		return $this->get_cart_points_total($exclude_product->get_id());
	}

	/**
	 * Calculate potential total cost if product is added to cart
	 *
	 * @param WC_Product $product Product to potentially add
	 * @param int $quantity Quantity to add (default: 1)
	 * @return float Potential total cost including cart + new product
	 */
	public function get_potential_total_cost(WC_Product $product, int $quantity = 1): float
	{
		$product_cost = $this->get_product_point_cost($product);

		if ($product_cost === null || $product_cost < 0) {
			return $this->get_cart_points_total();
		}

		// Check if this product is already in the cart
		$is_in_cart = $this->is_product_in_cart($product);
		
		if ($is_in_cart) {
			// Product is already in cart, so cart total already includes it
			// Just return the current cart total
			$potential_total = $this->get_cart_points_total();
			
			mycred_debug('Product already in cart - using current cart total', [
				'product_id' => $product->get_id(),
				'product_cost' => $product_cost,
				'cart_total' => $potential_total,
				'potential_total' => $potential_total
			], 'calculator', 'verbose');
		} else {
			// Product not in cart, add it to current total
			$current_cart_total = $this->get_cart_points_total();
			$potential_total = $current_cart_total + ($product_cost * $quantity);
			
			mycred_debug('Adding product to cart total', [
				'product_id' => $product->get_id(),
				'product_cost' => $product_cost,
				'quantity' => $quantity,
				'cart_total' => $current_cart_total,
				'potential_total' => $potential_total
			], 'calculator', 'verbose');
		}

		return $potential_total;
	}

	/**
	 * Check if a product is already in the cart
	 *
	 * @param WC_Product $product Product to check
	 * @return bool True if product is in cart
	 */
	private function is_product_in_cart(WC_Product $product): bool
	{
		if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
			return false;
		}

		$product_id = $product->get_id();

		foreach (WC()->cart->get_cart() as $cart_item) {
			$cart_product = $cart_item['data'];
			if ($cart_product->get_id() === $product_id) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get user's current point balance
	 *
	 * @param int|null $user_id User ID (defaults to current user)
	 * @return float User's point balance
	 */
	public function get_user_balance(?int $user_id = null): float
	{
		$user_id ??= get_current_user_id();

		if (!$user_id) {
			return 0.0;
		}

		$manager = MyCred_Manager::get_instance();
		$point_type_key = $manager->get_woo_point_type();

		if (empty($point_type_key)) {
			mycred_debug('No point type key found', $point_type_key, 'calculator', 'error');
			return 0.0;
		}

		$point_type_object = mycred($point_type_key);

		if (!$point_type_object || !method_exists($point_type_object, 'get_users_balance')) {
			mycred_debug('Invalid point type object', $point_type_key, 'calculator', 'error');
			return 0.0;
		}

		$balance = (float) $point_type_object->get_users_balance($user_id);

		return $balance;
	}

	/**
	 * Check if user can afford a product considering current cart
	 *
	 * @param WC_Product $product Product to check
	 * @param int|null $user_id User ID (defaults to current user)
	 * @param int $quantity Quantity to check (default: 1)
	 * @return bool True if user can afford the product
	 */
	public function can_user_afford_product(WC_Product $product, ?int $user_id = null, int $quantity = 1): bool
	{
		$user_balance = $this->get_user_balance($user_id);
		$potential_total = $this->get_potential_total_cost($product, $quantity);
		$can_afford = $potential_total <= $user_balance;

		// Log detailed affordability data for debugging (rate limited)
		mycred_debug('Affordability check', [
			'product_id' => $product->get_id(),
			'user_balance' => $user_balance,
			'potential_total' => $potential_total,
			'can_afford' => $can_afford,
			'calculation' => sprintf('%.2f <= %.2f', $potential_total, $user_balance)
		], 'calculator', 'verbose');

		return $can_afford;
	}
}
