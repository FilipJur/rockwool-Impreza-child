<?php

declare(strict_types=1);

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

    public function __construct(
        private readonly MyCred_Cart_Calculator $calculator
    ) {}

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks(): void {
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
    public function check_product_affordability(bool $is_purchasable, WC_Product $product): bool {
        // Log the incoming purchasability status and our decision
        mycred_debug('Purchasability filter called', [
            'product_id' => $product->get_id(),
            'incoming_purchasable' => $is_purchasable,
            'product_type' => $product->get_type(),
            'product_status' => $product->get_status()
        ], 'purchasability', 'verbose');

        if (!$is_purchasable || !$this->should_check_affordability($product)) {
            mycred_debug('Skipping myCred check', [
                'reason' => !$is_purchasable ? 'already_unpurchasable' : 'not_logged_in_or_no_mycred',
                'is_purchasable' => $is_purchasable
            ], 'purchasability', 'verbose');
            return $is_purchasable;
        }

        $user_id = get_current_user_id();
        $product_cost = $this->calculator->get_product_point_cost($product);

        if ($product_cost === null) {
            mycred_debug('Cannot determine product cost, allowing purchase', $product->get_id(), 'purchasability', 'warning');
            return $is_purchasable;
        }

        $can_afford = $this->calculator->can_user_afford_product($product, $user_id);

        mycred_debug('myCred purchasability decision', [
            'product_id' => $product->get_id(),
            'incoming_purchasable' => $is_purchasable,
            'can_afford' => $can_afford,
            'final_result' => $can_afford
        ], 'purchasability', 'info');

        return $can_afford;
    }

    /**
     * Determine if we should check affordability for this product
     */
    private function should_check_affordability(WC_Product $product): bool {
        return is_user_logged_in() && function_exists('mycred');
    }


    /**
     * Check if specific product is affordable for user
     *
     * Public method for external use.
     */
    public function is_product_affordable(WC_Product|int $product, ?int $user_id = null): bool {
        if (!$product instanceof WC_Product) {
            $product = wc_get_product($product);
        }

        if (!$product || !$this->should_check_affordability($product)) {
            return true; // Default to affordable if we can't check
        }

        return $this->calculator->can_user_afford_product($product, $user_id);
    }

}
