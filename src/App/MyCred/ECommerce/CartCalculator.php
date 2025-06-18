<?php

declare(strict_types=1);

namespace MistrFachman\MyCred\ECommerce;

/**
 * Cart Calculator Service
 *
 * Handles cart total calculations and product price analysis.
 * Extracted from CartContext to improve modularity and maintainability.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CartCalculator {

    /**
     * Calculate total points cost for current cart
     *
     * @return float Total points required for cart
     */
    public function calculateCartTotal(): float {
        if (!$this->isWooCommerceAvailable()) {
            return 0.0;
        }

        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return 0.0;
        }

        mycred_debug('Starting cart calculation', [
            'cart_items_count' => $cart->get_cart_contents_count()
        ], 'cart_context', 'info');

        $total_points = 0.0;

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];

            if (!$product instanceof \WC_Product) {
                continue;
            }

            $price_analysis = $this->analyzeProductPrice($product);
            $unit_cost = $price_analysis['final_cost'];
            $line_total = $unit_cost * $quantity;

            mycred_debug('Processing cart item', [
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'quantity' => $quantity,
                'unit_cost_points' => $unit_cost,
                'line_total_points' => $line_total
            ], 'cart_context', 'info');

            $total_points += $line_total;
        }

        mycred_debug('Cart total calculated', [
            'total_points' => $total_points,
            'cart_item_count' => $cart->get_cart_contents_count()
        ], 'cart_context', 'info');

        return $total_points;
    }

    /**
     * Analyze product price and convert to points
     *
     * @param \WC_Product $product WooCommerce product
     * @return array Price analysis with final cost in points
     */
    public function analyzeProductPrice(\WC_Product $product): array {
        $price = $product->get_price();
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();

        // Convert price to float, handling both string and numeric values
        $final_cost = 0.0;
        if (is_numeric($price)) {
            $final_cost = (float) $price;
        }

        $analysis = [
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'price' => $price,
            'regular_price' => $regular_price,
            'sale_price' => $sale_price,
            'price_type' => gettype($price),
            'is_numeric' => is_numeric($price),
            'final_cost' => $final_cost
        ];

        mycred_debug('Product price analysis', $analysis, 'cart_context', 'info');

        return $analysis;
    }

    /**
     * Get detailed cart items with point calculations
     *
     * @return array Array of cart items with point costs
     */
    public function getCartItemsWithPoints(): array {
        if (!$this->isWooCommerceAvailable()) {
            return [];
        }

        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return [];
        }

        $items = [];

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];

            if (!$product instanceof \WC_Product) {
                continue;
            }

            $price_analysis = $this->analyzeProductPrice($product);
            $unit_cost = $price_analysis['final_cost'];
            $line_total = $unit_cost * $quantity;

            $items[] = [
                'cart_item_key' => $cart_item_key,
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'quantity' => $quantity,
                'unit_cost_points' => $unit_cost,
                'line_total_points' => $line_total,
                'price_analysis' => $price_analysis
            ];
        }

        return $items;
    }

    /**
     * Check if a specific product can be afforded with given points
     *
     * @param \WC_Product $product Product to check
     * @param float $available_points Available points
     * @return bool True if product is affordable
     */
    public function canAffordProduct(\WC_Product $product, float $available_points): bool {
        $price_analysis = $this->analyzeProductPrice($product);
        $product_cost = $price_analysis['final_cost'];

        return $available_points >= $product_cost;
    }

    /**
     * Get cart statistics
     *
     * @return array Cart statistics including totals and item counts
     */
    public function getCartStatistics(): array {
        if (!$this->isWooCommerceAvailable()) {
            return [
                'total_points' => 0.0,
                'item_count' => 0,
                'unique_products' => 0,
                'is_empty' => true
            ];
        }

        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return [
                'total_points' => 0.0,
                'item_count' => 0,
                'unique_products' => 0,
                'is_empty' => true
            ];
        }

        return [
            'total_points' => $this->calculateCartTotal(),
            'item_count' => $cart->get_cart_contents_count(),
            'unique_products' => count($cart->get_cart()),
            'is_empty' => false
        ];
    }

    /**
     * Check if WooCommerce is available and functional
     *
     * @return bool True if WooCommerce is available
     */
    private function isWooCommerceAvailable(): bool {
        return function_exists('WC') && WC() !== null;
    }
}