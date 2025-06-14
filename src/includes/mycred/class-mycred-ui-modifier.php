<?php

declare(strict_types=1);

/**
 * MyCred UI Modifier Class
 *
 * Handles modifications to WooCommerce UI elements based on
 * myCred point affordability.
 *
 * @package impreza-child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MyCred_UI_Modifier {

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks(): void {
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'modify_add_to_cart_button'], 20, 3);
    }

    /**
     * Modify "Add to Cart" button text and styling when not affordable
     */
    public function modify_add_to_cart_button(string $html_link, WC_Product $product, array $args): string {
        if ($product->is_purchasable()) {
            return $html_link;
        }

        // Only modify simple, external, or grouped products
        if (!($product->is_type('simple') || $product->is_type('external') || $product->is_type('grouped'))) {
            return '';
        }

        $message = __('Not enough points (inc. cart)', 'woocommerce');
        $padding = $args['attributes']['style'] ?? '0.618em 1em';

        $button_html = sprintf(
            '<span class="button disabled mycred-insufficient-points" style="%s">%s</span>',
            esc_attr(implode(' ', [
                'background-color: #e0e0e0 !important;',
                'color: #888888 !important;',
                'cursor: not-allowed !important;',
                'padding: ' . $padding . ' !important;',
                'display: inline-block;',
                'text-align: center;',
                'border: none;',
                'text-decoration: none;'
            ])),
            esc_html($message)
        );

        // Log once per session for UI changes (rate limited)
        mycred_debug('Product button disabled due to insufficient points', $product->get_id(), 'ui-modifier', 'verbose');

        return $button_html;
    }


    /**
     * Add custom CSS for disabled buttons
     */
    public function add_custom_styles(): void {
        $css = '
        .mycred-insufficient-points {
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }
        .mycred-insufficient-points:hover {
            opacity: 0.8;
        }
        ';

        wp_add_inline_style('woocommerce-general', $css);
    }

    /**
     * Get affordability message for a specific product
     */
    public function get_affordability_message(WC_Product $product): string {
        $manager = MyCred_Manager::get_instance();
        $calculator = $manager->cart_calculator;

        $user_balance = $calculator->get_user_balance();
        $product_cost = $calculator->get_product_point_cost($product);
        $potential_total = $calculator->get_potential_total_cost($product);

        if ($product_cost === null || $potential_total <= $user_balance) {
            return '';
        }

        $needed = $potential_total - $user_balance;
        return sprintf(
            __('You need %d more points to purchase this item (including cart contents).', 'woocommerce'),
            (int)ceil($needed)
        );
    }
}
