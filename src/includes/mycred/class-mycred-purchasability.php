<?php
/**
 * MyCred Purchasability Class
 *
 * Handles product purchasability logic based on myCred points
 * and current cart contents.
 *
 * @package impreza-child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MyCred_Purchasability {

    /**
     * Cart calculator instance
     *
     * @var MyCred_Cart_Calculator
     */
    private $calculator;

    /**
     * Constructor
     *
     * @param MyCred_Cart_Calculator $calculator Cart calculator instance
     */
    public function __construct(MyCred_Cart_Calculator $calculator) {
        $this->calculator = $calculator;
    }

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks() {
        add_filter('woocommerce_is_purchasable', [$this, 'check_product_affordability'], 10, 2);
        add_filter('woocommerce_variation_is_purchasable', [$this, 'check_product_affordability'], 10, 2);
    }

    /**
     * Check if product is affordable based on myCred points and current cart
     *
     * This is the main filter that determines product purchasability.
     *
     * @param bool $is_purchasable Current purchasability status
     * @param WC_Product $product Product object
     * @return bool Modified purchasability status
     */
    public function check_product_affordability($is_purchasable, $product) {
        // If product is already not purchasable for other reasons, don't change it
        if (!$is_purchasable) {
            return false;
        }

        // Only work with logged-in users and when myCred is active
        if (!$this->should_check_affordability($product)) {
            return $is_purchasable;
        }

        $user_id = get_current_user_id();
        $product_cost = $this->calculator->get_product_point_cost($product);

        // If we can't determine product cost, don't change purchasability
        if (is_null($product_cost)) {
            return $is_purchasable;
        }

        // Check if user can afford this product considering their cart
        if (!$this->calculator->can_user_afford_product($product, $user_id)) {
            return false;
        }

        return $is_purchasable;
    }

    /**
     * Determine if we should check affordability for this product
     *
     * @param WC_Product $product Product to check
     * @return bool True if we should check affordability
     */
    private function should_check_affordability($product) {
        return is_user_logged_in()
            && function_exists('mycred')
            && $product instanceof WC_Product;
    }


    /**
     * Check if specific product is affordable for user
     *
     * Public method for external use.
     *
     * @param WC_Product|int $product Product object or ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if affordable
     */
    public function is_product_affordable($product, $user_id = null) {
        if (!$product instanceof WC_Product) {
            $product = wc_get_product($product);
        }

        if (!$product || !$this->should_check_affordability($product)) {
            return true; // Default to affordable if we can't check
        }

        return $this->calculator->can_user_afford_product($product, $user_id);
    }

}
