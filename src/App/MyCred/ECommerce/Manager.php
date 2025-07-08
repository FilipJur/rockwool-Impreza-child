<?php

declare(strict_types=1);

namespace MistrFachman\MyCred\ECommerce;

use MistrFachman\Services\UserDetectionService;
use MistrFachman\Services\PointTypeConstants;


/**
 * MyCred Manager Class
 *
 * Main orchestrator for myCred WooCommerce integration.
 * Handles initialization and coordination between components.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Manager {

    private static ?self $instance = null;
    private static ?string $cached_point_type = null;

    public CartContext $cart_context;
    public BalanceCalculator $balance_calculator;
    public Purchasability $purchasability;
    public UiModifier $ui_modifier;

    /**
     * Get singleton instance with dependency injection
     */
    public static function get_instance(?UserDetectionService $user_detection_service = null): self {
        if (self::$instance === null) {
            self::$instance = new self($user_detection_service);
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     * Now accepts shared services via dependency injection
     */
    private function __construct(?UserDetectionService $user_detection_service = null) {
        // Initialize components in dependency order
        $cart_calculator = new CartCalculator();
        $this->cart_context = new CartContext($cart_calculator);
        $this->balance_calculator = new BalanceCalculator($this->cart_context);
        $this->purchasability = new Purchasability($this->balance_calculator, $this);
        $this->ui_modifier = new UiModifier($this->balance_calculator, $this);

        $this->setup_hooks();

        mycred_debug('MyCred E-Commerce domain initialized', [
            'components' => [
                'cart_context' => get_class($this->cart_context),
                'balance_calculator' => get_class($this->balance_calculator),
                'purchasability' => get_class($this->purchasability),
                'ui_modifier' => get_class($this->ui_modifier)
            ]
        ], 'manager', 'info');
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks(): void {
        $this->purchasability->init_hooks();
        $this->ui_modifier->init_hooks();
    }

    /**
     * Get available points for current user (convenience method)
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return float Available points after cart is considered
     */
    public function get_available_points(?int $user_id = null): float {
        return $this->balance_calculator->get_available_points($user_id);
    }

    /**
     * Check if user can afford a product (convenience method)
     *
     * @param \WC_Product|int $product Product object or ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if user can afford the product
     */
    public function can_afford_product(\WC_Product|int $product, ?int $user_id = null): bool {
        return $this->balance_calculator->can_afford_product($product, $user_id);
    }

    /**
     * Get user's total point balance (convenience method)
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return float User's total point balance
     */
    public function get_user_balance(?int $user_id = null): float {
        return $this->balance_calculator->get_user_balance($user_id);
    }

    /**
     * Check if user has made any purchases (convenience method)
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if user has made at least one purchase
     */
    public function has_user_made_purchase(?int $user_id = null): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id || !function_exists('wc_get_orders')) {
            return false;
        }

        // Check for completed orders
        $orders = wc_get_orders([
            'customer' => $user_id,
            'status' => ['completed', 'processing'],
            'limit' => 1,
            'return' => 'ids'
        ]);

        return !empty($orders);
    }

    /**
     * Get myCred point type for WooCommerce
     *
     * Handles fallback logic for determining the correct point type.
     * Uses caching to avoid repeated detection and logging spam.
     */
    public function get_woo_point_type(): string {
        // Return cached result if available
        if (self::$cached_point_type !== null) {
            return self::$cached_point_type;
        }

        // Try preferred myCred function first
        if (function_exists('mycred_get_woo_point_type')) {
            $point_type = mycred_get_woo_point_type();
            if (!empty($point_type)) {
                self::$cached_point_type = $point_type;
                return $point_type;
            }
        }

        // Fallback to gateway settings
        $gateway_settings = get_option('woocommerce_mycred_settings', []);
        $point_type = match (true) {
            isset($gateway_settings['point_type']) => $gateway_settings['point_type'],
            defined('MYCRED_DEFAULT_TYPE_KEY') => MYCRED_DEFAULT_TYPE_KEY,
            default => PointTypeConstants::getDefaultPointType()
        };

        // Cache the result
        self::$cached_point_type = $point_type;

        // Log once that we're using fallback (only on first detection)
        if (!function_exists('mycred_get_woo_point_type')) {
            mycred_debug('Using fallback point type detection - myCred gateway function not available', $point_type, 'manager', 'warning');
        }

        return $point_type;
    }

    private function __clone() {}
    public function __wakeup(): void {
        throw new \Exception('Cannot unserialize singleton');
    }
}