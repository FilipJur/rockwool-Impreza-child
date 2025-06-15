<?php

declare(strict_types=1);

namespace MistrFachman\MyCred\ECommerce;

/**
 * MyCred UI Modifier Class
 *
 * Handles modifications to WooCommerce UI elements based on
 * myCred point affordability.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UiModifier {

    public function __construct(
        private BalanceCalculator $balance_calculator,
        private Manager $manager
    ) {}

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks(): void {
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'modify_add_to_cart_button'], 20, 3);
    }

    /**
     * Modify "Add to Cart" button text and styling when not affordable
     */
    public function modify_add_to_cart_button(string $html_link, \WC_Product $product, array $args): string {
        if ($product->is_purchasable()) {
            return $html_link;
        }

        // Only modify simple, external, or grouped products
        if (!($product->is_type('simple') || $product->is_type('external') || $product->is_type('grouped'))) {
            return '';
        }

        $message = $this->get_affordability_message($product);
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
     * Get appropriate Czech message based on affordability scenario
     */
    private function get_affordability_message(\WC_Product $product): string {
        if (!is_user_logged_in() || !function_exists('mycred')) {
            return 'Nedostatek bodů';
        }

        try {
            $user_id = get_current_user_id();
            $user_balance = $this->manager->get_user_balance($user_id);
            $product_price = (float) $product->get_price();
            
            // Scenario 1: Product price alone exceeds total balance
            if ($product_price > $user_balance) {
                return 'Nedostatek bodů';
            }
            
            // Scenario 2: Product is affordable alone, but not with current cart
            return 'Nedostatek bodů (vč. košíku)';
            
        } catch (\Exception $e) {
            mycred_debug('Error in affordability message calculation', [
                'product_id' => $product->get_id(),
                'error' => $e->getMessage()
            ], 'ui-modifier', 'error');
            
            return 'Nedostatek bodů';
        }
    }
}