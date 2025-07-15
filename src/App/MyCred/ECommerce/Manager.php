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
     * Get a comprehensive summary of the user's balance information
     *
     * Centralizes all balance-related calculations into a single method
     * to avoid duplication across shortcodes and components.
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return array{total: float, available: float, reserved: float, pending: int}
     */
    public function get_balance_summary(?int $user_id = null): array {
        $total_balance = $this->balance_calculator->get_user_balance($user_id);
        $available_points = $this->balance_calculator->get_available_points($user_id);
        $cart_reserved = $total_balance - $available_points;
        $pending_points = $this->get_pending_points($user_id);

        return [
            'total' => $total_balance,
            'available' => $available_points,
            'reserved' => $cart_reserved,
            'pending' => $pending_points,
        ];
    }

    /**
     * Get pending points for a user from submitted but not yet approved posts
     *
     * Queries both realizace and faktura post types with 'pending' status
     * and sums their points values using the appropriate field services.
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return int Total pending points awaiting approval
     */
    public function get_pending_points(?int $user_id = null): int {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            mycred_debug('get_pending_points: No user ID provided', [], 'ecommerce-manager', 'warning');
            return 0;
        }

        try {
            // Query pending realizace posts using proper services
            $realizace_posts = get_posts([
                'post_type' => \MistrFachman\Services\DomainConfigurationService::getWordPressPostType('realization'),
                'post_status' => 'pending',
                'author' => $user_id,
                'numberposts' => -1,
                'fields' => 'ids'
            ]);

            // Query pending faktura posts using proper services
            $faktura_posts = get_posts([
                'post_type' => \MistrFachman\Services\DomainConfigurationService::getWordPressPostType('invoice'),
                'post_status' => 'pending',
                'author' => $user_id,
                'numberposts' => -1,
                'fields' => 'ids'
            ]);

            $total_pending = 0;
            $debug_data = [
                'user_id' => $user_id,
                'realizace_posts' => $realizace_posts,
                'faktura_posts' => $faktura_posts,
                'realizace_points' => [],
                'faktura_points' => []
            ];

            // Sum points from realizace posts
            foreach ($realizace_posts as $post_id) {
                if (class_exists('\MistrFachman\Realizace\RealizaceFieldService')) {
                    $points = \MistrFachman\Realizace\RealizaceFieldService::getPoints($post_id);
                    $total_pending += $points;
                    $debug_data['realizace_points'][$post_id] = $points;
                } else {
                    mycred_debug('RealizaceFieldService class not found', [], 'ecommerce-manager', 'error');
                }
            }

            // Sum points from faktura posts
            foreach ($faktura_posts as $post_id) {
                if (class_exists('\MistrFachman\Faktury\FakturaFieldService')) {
                    $points = \MistrFachman\Faktury\FakturaFieldService::getPoints($post_id);
                    $total_pending += $points;
                    $debug_data['faktura_points'][$post_id] = $points;
                } else {
                    mycred_debug('FakturaFieldService class not found', [], 'ecommerce-manager', 'error');
                }
            }

            $debug_data['total_pending'] = $total_pending;

            // Log detailed debug information if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                mycred_debug('get_pending_points calculation', $debug_data, 'ecommerce-manager', 'info');
            }

            return $total_pending;

        } catch (\Exception $e) {
            mycred_debug('get_pending_points error', [
                'user_id' => $user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'ecommerce-manager', 'error');
            return 0;
        }
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