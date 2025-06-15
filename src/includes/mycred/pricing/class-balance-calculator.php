<?php

declare(strict_types=1);

/**
 * MyCred Available Balance Calculator Class
 *
 * Core component of the new architecture that provides a single source of truth
 * for calculating available points after considering current cart contents.
 *
 * This class solves the fundamental flaw in the old system where each product
 * was checked independently against the full user balance, ignoring the fact
 * that points are a finite global resource affected by cart contents.
 *
 * @package impreza-child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MyCred_Pricing_BalanceCalculator {

    private ?MyCred_Pricing_CartContext $cart_context = null;
    public function __construct(?MyCred_Pricing_CartContext $cart_context = null) {
        $this->cart_context = $cart_context ?? new MyCred_Pricing_CartContext();
    }

    /**
     * Get available points for user after considering current cart contents
     *
     * This is the core method that implements the new "cart-aware" logic:
     * Available Points = User Balance - Current Cart Total
     *
     * NOTE: This method does NOT cache results because cart state can change
     * frequently within a single request. The cart context handles its own caching.
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @param bool $force_refresh Force cache refresh before calculation
     * @return float Available points after cart is considered
     */
    public function get_available_points(?int $user_id = null, bool $force_refresh = false): float {
        $start_time = microtime(true);
        $user_id ??= get_current_user_id();

        if (!$user_id) {
            return 0.0;
        }


        try {
            $user_balance = $this->get_user_balance($user_id);
            $cart_total = $this->cart_context->get_current_cart_total();
            $available = max(0, $user_balance - $cart_total);

            mycred_debug('Available balance calculated', [
                'user_id' => $user_id,
                'user_balance' => $user_balance,
                'cart_total' => $cart_total,
                'available_points' => $available,
                'calculation' => sprintf('max(0, %.2f - %.2f) = %.2f', $user_balance, $cart_total, $available),
                'force_refresh' => $force_refresh
            ], 'balance_calculator', 'info');

            mycred_log_performance('get_available_points', $start_time, [
                'user_id' => $user_id,
                'cache_hit' => false,
                'user_balance' => $user_balance,
                'cart_total' => $cart_total,
                'result' => $available,
                'force_refresh' => $force_refresh
            ]);

            return $available;

        } catch (Exception $e) {
            mycred_debug('Error calculating available balance', [
                'user_id' => $user_id,
                'error' => $e->getMessage(),
                'force_refresh' => $force_refresh
            ], 'balance_calculator', 'error');

            mycred_log_performance('get_available_points', $start_time, [
                'user_id' => $user_id,
                'error' => true,
                'error_message' => $e->getMessage(),
                'force_refresh' => $force_refresh
            ]);

            // Fallback to full user balance if calculation fails
            return $this->get_user_balance($user_id);
        }
    }

    /**
     * Check if user can afford a specific product
     *
     * Simple comparison: product cost <= available points
     * This replaces the complex cart simulation logic in the old system.
     *
     * @param WC_Product|int $product Product object or ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if user can afford the product
     */
    public function can_afford_product(WC_Product|int $product, ?int $user_id = null): bool {
        if (!$product instanceof WC_Product) {
            $product = wc_get_product($product);
        }

        if (!$product) {
            mycred_debug('Invalid product for affordability check', $product, 'balance_calculator', 'warning');
            return false;
        }

        $product_cost = $this->get_product_point_cost($product);
        if ($product_cost === null || $product_cost <= 0) {
            // Free products or products without valid cost are always affordable
            return true;
        }

        $available_points = $this->get_available_points($user_id);
        $can_afford = $product_cost <= $available_points;

        mycred_debug('Product affordability check', [
            'product_id' => $product->get_id(),
            'product_cost' => $product_cost,
            'available_points' => $available_points,
            'can_afford' => $can_afford,
            'comparison' => sprintf('%.2f <= %.2f', $product_cost, $available_points)
        ], 'balance_calculator', 'info');

        return $can_afford;
    }

    /**
     * Check if user can afford multiple products (bulk operation)
     *
     * More efficient than checking each product individually.
     *
     * @param array $products Array of WC_Product objects or IDs
     * @param int|null $user_id User ID (defaults to current user)
     * @return array Associative array [product_id => can_afford_bool]
     */
    public function can_afford_products(array $products, ?int $user_id = null): array {
        $available_points = $this->get_available_points($user_id);
        $results = [];

        foreach ($products as $product) {
            if (!$product instanceof WC_Product) {
                $product = wc_get_product($product);
            }

            if (!$product) {
                continue;
            }

            $product_cost = $this->get_product_point_cost($product);
            $results[$product->get_id()] = ($product_cost !== null && $product_cost > 0)
                ? ($product_cost <= $available_points)
                : true; // Free products are always affordable
        }

        mycred_debug('Bulk affordability check', [
            'product_count' => count($products),
            'available_points' => $available_points,
            'results' => $results
        ], 'balance_calculator', 'info');

        return $results;
    }

    /**
     * Get user's current point balance
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return float User's point balance
     */
    public function get_user_balance(?int $user_id = null): float {
        $user_id ??= get_current_user_id();

        if (!$user_id) {
            return 0.0;
        }


        try {
            $manager = MyCred_Pricing_Manager::get_instance();
            $point_type_key = $manager->get_woo_point_type();

            if (empty($point_type_key)) {
                mycred_debug('No point type key found', $point_type_key, 'balance_calculator', 'error');
                return 0.0;
            }

            $point_type_object = mycred($point_type_key);

            if (!$point_type_object || !method_exists($point_type_object, 'get_users_balance')) {
                mycred_debug('Invalid point type object', $point_type_key, 'balance_calculator', 'error');
                return 0.0;
            }

            $balance = (float) $point_type_object->get_users_balance($user_id);


            return $balance;

        } catch (Exception $e) {
            mycred_debug('Error getting user balance', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ], 'balance_calculator', 'error');

            return 0.0;
        }
    }

    /**
     * Get effective point cost for a product
     *
     * @param WC_Product $product Product object
     * @return float|null Product cost in points, null if invalid
     */
    private function get_product_point_cost(WC_Product $product): ?float {
        $price = $product->get_price();
        $cost = ($price !== '' && is_numeric($price)) ? (float)$price : null;

        if ($cost === null && $price !== '') {
            mycred_debug('Invalid product price detected', [
                'product_id' => $product->get_id(),
                'price' => $price
            ], 'balance_calculator', 'warning');
        }

        return $cost;
    }

    /**
     * Get total point value of current cart (delegated)
     *
     * @return float Total points value of cart contents
     */
    public function get_cart_total(): float {
        return $this->cart_context->get_current_cart_total();
    }

    /**
     * Test helper: Check if user can afford a specific cost amount
     *
     * This is useful for testing scenarios without needing actual products.
     *
     * @param float $cost Point cost to check
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if user can afford the cost
     */
    public function can_afford_product_by_cost(float $cost, ?int $user_id = null): bool {
        if ($cost <= 0) {
            return true; // Free is always affordable
        }

        $available_points = $this->get_available_points($user_id);
        return $cost <= $available_points;
    }
}
