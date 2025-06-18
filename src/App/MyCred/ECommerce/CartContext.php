<?php

declare(strict_types=1);

namespace MistrFachman\MyCred\ECommerce;

/**
 * MyCred Cart Context Class
 *
 * Centralized cart state management for the myCred integration.
 * Ensures all components use the same cart state and provides caching
 * to avoid repeated cart calculations.
 *
 * This class uses native WooCommerce cart methods exclusively and provides
 * a consistent interface for cart-related operations across the system.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CartContext {

    public function __construct(
        private ?CartCalculator $cart_calculator = null
    ) {
        // Fallback for backward compatibility
        if ($this->cart_calculator === null) {
            $this->cart_calculator = new CartCalculator();
        }
    }

    /**
     * Get total point value of current cart
     *
     * Uses CartCalculator service for calculation logic.
     *
     * @return float Total points value of cart contents
     */
    public function get_current_cart_total(): float {
        if (!$this->is_cart_available()) {
            return 0.0;
        }

        return $this->cart_calculator->calculateCartTotal();
    }

    /**
     * Check if a product is currently in the cart
     *
     * @param int $product_id Product ID to check
     * @return bool True if product is in cart
     */
    public function is_product_in_cart(int $product_id): bool {
        if (!$this->is_cart_available()) {
            return false;
        }

        $is_in_cart = false;

        foreach (\WC()->cart->get_cart() as $cart_item) {
            $cart_product = $cart_item['data'];
            if ($cart_product instanceof \WC_Product && $cart_product->get_id() === $product_id) {
                $is_in_cart = true;
                break;
            }
        }

        return $is_in_cart;
    }

    /**
     * Get all cart items that have point costs
     *
     * @return array Array of cart items with point cost information
     */
    public function get_cart_point_items(): array {
        if (!$this->is_cart_available()) {
            return [];
        }

        $point_items = [];

        foreach (\WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

            if (!$product instanceof \WC_Product) {
                continue;
            }

            $product_cost_points = $this->get_product_point_cost($product);
            $quantity = (int) ($cart_item['quantity'] ?? 0);

            if ($product_cost_points !== null && $product_cost_points > 0) {
                $point_items[] = [
                    'cart_item_key' => $cart_item_key,
                    'product_id' => $product->get_id(),
                    'product_name' => $product->get_name(),
                    'quantity' => $quantity,
                    'unit_cost_points' => $product_cost_points,
                    'total_cost_points' => $product_cost_points * $quantity
                ];
            }
        }

        mycred_debug('Cart point items retrieved', [
            'item_count' => count($point_items),
            'items' => $point_items
        ], 'cart_context', 'verbose');

        return $point_items;
    }

    /**
     * Get cart total excluding a specific product
     *
     * Useful for checking if other products would still be affordable
     * if a specific product were removed from the cart.
     *
     * @param int $exclude_product_id Product ID to exclude
     * @return float Cart total excluding the specified product
     */
    public function get_cart_total_excluding_product(int $exclude_product_id): float {
        if (!$this->is_cart_available()) {
            return 0.0;
        }

        $cart_total_points = 0.0;

        foreach (\WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

            if (!$product instanceof \WC_Product) {
                continue;
            }

            // Skip the excluded product
            if ($product->get_id() === $exclude_product_id) {
                continue;
            }

            $product_cost_points = $this->get_product_point_cost($product);
            $quantity = (int) ($cart_item['quantity'] ?? 0);

            if ($product_cost_points !== null && $quantity > 0) {
                $cart_total_points += ($product_cost_points * $quantity);
            }
        }

        return $cart_total_points;
    }

    /**
     * Check if WooCommerce cart is available and not empty
     *
     * @return bool True if cart is available
     */
    private function is_cart_available(): bool {
        $wc_exists = function_exists('WC');
        $cart_exists = $wc_exists && \WC()->cart;
        $cart_not_empty = $cart_exists && !\WC()->cart->is_empty();
        $cart_contents_count = $cart_exists ? \WC()->cart->get_cart_contents_count() : 0;

        // Enhanced debugging for cart availability
        mycred_debug('Cart availability check', [
            'wc_function_exists' => $wc_exists,
            'cart_object_exists' => $cart_exists,
            'cart_is_empty' => $cart_exists ? \WC()->cart->is_empty() : 'N/A',
            'cart_contents_count' => $cart_contents_count,
            'final_result' => $cart_not_empty
        ], 'cart_context', 'info');

        return $cart_not_empty;
    }

    /**
     * Get effective point cost for a product
     * 
     * @deprecated Use CartCalculator::analyzeProductPrice() instead
     * @param \WC_Product $product Product object
     * @return float|null Product cost in points, null if invalid
     */
    private function get_product_point_cost(\WC_Product $product): ?float {
        $analysis = $this->cart_calculator->analyzeProductPrice($product);
        return $analysis['final_cost'] > 0 ? $analysis['final_cost'] : null;
    }

    /**
     * Diagnostic method to troubleshoot cart calculation issues
     *
     * This method provides detailed information about the current cart state
     * and is intended for debugging purposes.
     *
     * @return array Comprehensive cart diagnostic information
     */
    public function get_cart_diagnostics(): array {
        $diagnostics = [
            'timestamp' => current_time('mysql'),
            'woocommerce_status' => [
                'wc_function_exists' => function_exists('WC'),
                'wc_object_exists' => function_exists('WC') && \WC(),
                'cart_object_exists' => function_exists('WC') && \WC() && \WC()->cart,
            ],
            'cart_status' => [],
            'cart_items' => [],
            'calculations' => []
        ];

        if (function_exists('WC') && \WC() && \WC()->cart) {
            $cart = \WC()->cart;
            $diagnostics['cart_status'] = [
                'is_empty' => $cart->is_empty(),
                'contents_count' => $cart->get_cart_contents_count(),
                'contents_total' => $cart->get_cart_contents_total(),
                'subtotal' => $cart->get_subtotal(),
                'total' => $cart->get_total('edit')
            ];

            $total_points = 0.0;
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                $product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

                $item_info = [
                    'cart_item_key' => $cart_item_key,
                    'quantity' => $cart_item['quantity'] ?? 0,
                    'product_valid' => $product instanceof \WC_Product
                ];

                if ($product instanceof \WC_Product) {
                    $product_cost = $this->get_product_point_cost($product);
                    $item_info = array_merge($item_info, [
                        'product_id' => $product->get_id(),
                        'product_name' => $product->get_name(),
                        'product_type' => $product->get_type(),
                        'price' => $product->get_price(),
                        'regular_price' => $product->get_regular_price(),
                        'sale_price' => $product->get_sale_price(),
                        'point_cost' => $product_cost,
                        'line_total' => $product_cost !== null ? ($product_cost * $item_info['quantity']) : null
                    ]);

                    if ($product_cost !== null && $item_info['quantity'] > 0) {
                        $total_points += ($product_cost * $item_info['quantity']);
                    }
                }

                $diagnostics['cart_items'][] = $item_info;
            }

            $diagnostics['calculations'] = [
                'calculated_total_points' => $total_points,
                'cached_total' => 'caching_disabled',
                'is_cart_available' => $this->is_cart_available()
            ];
        }

        return $diagnostics;
    }
}
