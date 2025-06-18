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
     * Check if current user is pending approval
     */
    private function is_user_pending(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array('pending_approval', $user->roles, true);
    }

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks(): void {
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'modify_add_to_cart_button'], 20, 3);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_custom_styles']);
    }

    /**
     * Enqueue custom styles for disabled buttons
     */
    public function enqueue_custom_styles(): void {
        if (is_woocommerce() || is_cart() || is_checkout() || is_shop() || is_product_category() || is_product_tag()) {
            $this->add_custom_styles();
        }
    }

    /**
     * Modify "Add to Cart" button text and styling when not purchasable
     */
    public function modify_add_to_cart_button(string $html_link, \WC_Product $product, array $args): string {
        // Check if user is pending - this takes priority over affordability
        if ($this->is_user_pending()) {
            return $this->render_disabled_button($product, $args, 'pending');
        }

        // Check standard purchasability (includes affordability)
        if ($product->is_purchasable()) {
            return $html_link;
        }

        // Handle insufficient points case
        return $this->render_disabled_button($product, $args, 'insufficient_points');
    }

    /**
     * Render disabled button with appropriate styling and message
     */
    private function render_disabled_button(\WC_Product $product, array $args, string $reason): string {
        // Only modify simple, external, or grouped products
        if (!($product->is_type('simple') || $product->is_type('external') || $product->is_type('grouped'))) {
            return '';
        }

        $message = $this->get_disabled_button_message($product, $reason);
        $css_class = $this->get_disabled_button_css_class($reason);
        $padding = $args['attributes']['style'] ?? '0.618em 1em';

        $button_html = sprintf(
            '<span class="button disabled %s" style="%s">%s</span>',
            esc_attr($css_class),
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

        // Log the reason for disabling
        mycred_debug('Product button disabled', [
            'product_id' => $product->get_id(),
            'reason' => $reason,
            'user_id' => get_current_user_id()
        ], 'ui-modifier', 'verbose');

        return $button_html;
    }

    /**
     * Add custom CSS for disabled buttons
     */
    public function add_custom_styles(): void {
        $css = '
        .mycred-insufficient-points,
        .mycred-pending-user {
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }
        .mycred-insufficient-points:hover,
        .mycred-pending-user:hover {
            opacity: 0.8;
        }
        .mycred-pending-user {
            background-color: #fff3cd !important;
            color: #856404 !important;
            border: 1px solid #ffeaa7 !important;
        }
        ';

        wp_add_inline_style('woocommerce-general', $css);
    }

    /**
     * Get appropriate message for disabled button
     */
    private function get_disabled_button_message(\WC_Product $product, string $reason): string {
        return match ($reason) {
            'pending' => $this->get_pending_user_message($product),
            'insufficient_points' => $this->get_affordability_message($product),
            default => 'Nedostupné'
        };
    }

    /**
     * Get CSS class for disabled button
     */
    private function get_disabled_button_css_class(string $reason): string {
        return match ($reason) {
            'pending' => 'mycred-pending-user',
            'insufficient_points' => 'mycred-insufficient-points',
            default => 'mycred-disabled'
        };
    }

    /**
     * Get message for pending users
     */
    private function get_pending_user_message(\WC_Product $product): string {
        // Get user's registration status for more specific messaging
        if (class_exists('\MistrFachman\Users\Manager')) {
            $users_manager = \MistrFachman\Users\Manager::get_instance();
            $user_service = $users_manager->get_user_service();
            $status = $user_service->get_user_registration_status(get_current_user_id());
            
            return match ($status) {
                'needs_form' => 'Dokončete registraci',
                'awaiting_review' => 'Čeká na schválení',
                default => 'Registrace probíhá'
            };
        }
        
        return 'Registrace probíhá';
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
            $available_balance = $this->balance_calculator->get_available_points($user_id);
            $product_price = (float) $product->get_price();
            
            // Scenario 1: Product price alone exceeds total balance
            if ($product_price > $user_balance) {
                return 'Nedostatek bodů';
            }
            
            // Scenario 2: Product is affordable alone, but not with current cart
            if ($product_price <= $user_balance && $product_price > $available_balance) {
                return 'Nedostatek bodů (vč. košíku)';
            }
            
            // Fallback - shouldn't reach here if purchasability logic is correct
            return 'Nedostatek bodů';
            
        } catch (\Exception $e) {
            mycred_debug('Error in affordability message calculation', [
                'product_id' => $product->get_id(),
                'error' => $e->getMessage()
            ], 'ui-modifier', 'error');
            
            return 'Nedostatek bodů';
        }
    }
}