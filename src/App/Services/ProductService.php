<?php
namespace MistrFachman\Services;

use MistrFachman\MyCred\ECommerce\Manager;

/**
 * Product Service Class
 *
 * Handles all business logic related to fetching and filtering WooCommerce products.
 * It is dependency-aware and uses the ECommerce Manager for affordability checks.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */
class ProductService {

    private Manager $ecommerce_manager;

    public function __construct(Manager $ecommerce_manager) {
        $this->ecommerce_manager = $ecommerce_manager;
    }

    /**
     * Fetches products from WooCommerce with given arguments.
     *
     * @param array $args Arguments for wc_get_products.
     * @return \WC_Product[]
     */
    public function get_products(array $args = []): array {
        $defaults = [
            'status' => 'publish',
            'limit' => -1,
            'return' => 'objects',
        ];
        $query_args = wp_parse_args($args, $defaults);
        
        try {
            return wc_get_products($query_args);
        } catch (\Exception $e) {
            mycred_debug('Error in ProductService::get_products', [
                'args' => $query_args,
                'error' => $e->getMessage()
            ], 'product_service', 'error');
            return [];
        }
    }

    /**
     * Gets products and filters them by affordability using the myCred E-Commerce logic.
     * This is the "intelligent" method that shortcodes and other components will use.
     *
     * @param string $filter_type 'all', 'affordable', or 'unavailable'.
     * @param array $query_args Arguments for the underlying wc_get_products query.
     * @param int|null $user_id User ID (defaults to current user)
     * @return \WC_Product[] The filtered list of WC_Product objects.
     */
    public function get_products_filtered_by_balance(string $filter_type, array $query_args = [], ?int $user_id = null): array {
        // Remove limit from query args - we'll handle limiting at the shortcode level
        $query_args_without_limit = $query_args;
        unset($query_args_without_limit['limit']);
        
        $all_products = $this->get_products($query_args_without_limit);

        if ($filter_type === 'all' || empty($all_products)) {
            return $all_products;
        }

        // Use current user if not specified
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // If user is not logged in, return empty for 'affordable' filter
        if ($user_id === 0 && $filter_type === 'affordable') {
            return [];
        }

        $filtered_products = [];
        foreach ($all_products as $product) {
            if (!$product instanceof \WC_Product) continue;

            // Use the injected manager to access the core affordability logic with explicit user_id
            $is_affordable = $this->ecommerce_manager->can_afford_product($product, $user_id);

            if (($filter_type === 'affordable' && $is_affordable) || ($filter_type === 'unavailable' && !$is_affordable)) {
                $filtered_products[] = $product;
            }
        }

        return $filtered_products;
    }

    /**
     * Gets the next product that the user cannot afford
     *
     * This represents the user's "next goal" and is determined by finding the
     * cheapest product that is currently outside their points range.
     * Used for progress tracking and motivation features.
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return \WC_Product|null The product object or null if all products are affordable
     */
    public function get_next_unaffordable_product(?int $user_id = null): ?\WC_Product {
        // Use current user if not specified
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $unaffordable_products = $this->get_products_filtered_by_balance('unavailable', [], $user_id);

        if (empty($unaffordable_products)) {
            return null;
        }

        // Sort by price ASC to find the cheapest unaffordable product
        usort($unaffordable_products, function($a, $b) {
            return (float)$a->get_price() <=> (float)$b->get_price();
        });

        return $unaffordable_products[0];
    }

    /**
     * Gets products using dynamic filtering strategy
     *
     * Simple logic: If user can afford all products, show available products (highest to lowest).
     * If user cannot afford all products, show unavailable products (lowest to highest - closest goals first).
     *
     * @param int $user_id User ID
     * @param array $query_args Query arguments with limit
     * @return \WC_Product[] Array of products
     */
    public function get_dynamic_product_selection(int $user_id, array $query_args): array {
        // Check if user can afford all products
        if ($this->can_user_afford_all_products($user_id)) {
            // Show available products (highest to lowest - best rewards first)
            return $this->get_affordable_products($user_id, $query_args);
        } else {
            // Show unavailable products (lowest to highest - closest goals first)
            return $this->get_unaffordable_products($user_id, $query_args);
        }
    }

    /**
     * Gets affordable products for a user (explicit API method)
     *
     * Clean, intention-revealing method that returns products the user can afford.
     * Applies ordering automatically: highest to lowest price (best rewards first).
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @param array $query_args Additional WooCommerce query arguments
     * @return \WC_Product[] Array of affordable products
     */
    public function get_affordable_products(?int $user_id = null, array $query_args = []): array {
        $user_id ??= get_current_user_id();
        
        // Don't rely on WooCommerce ordering - get products without order and sort manually
        $products = $this->get_products_filtered_by_balance('affordable', $query_args, $user_id);
        
        // Manual sort by price DESC (highest to lowest - best rewards first)
        usort($products, function($a, $b) {
            return (float)$b->get_price() <=> (float)$a->get_price();
        });
        
        return $products;
    }

    /**
     * Gets unaffordable products for a user (explicit API method)
     *
     * Clean, intention-revealing method that returns products the user cannot afford.
     * Applies ordering automatically: lowest to highest price (closest goals first).
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @param array $query_args Additional WooCommerce query arguments
     * @return \WC_Product[] Array of unaffordable products
     */
    public function get_unaffordable_products(?int $user_id = null, array $query_args = []): array {
        $user_id ??= get_current_user_id();
        
        // Don't rely on WooCommerce ordering - get products without order and sort manually
        $products = $this->get_products_filtered_by_balance('unavailable', $query_args, $user_id);
        
        // Manual sort by price ASC (lowest to highest - closest goals first)
        usort($products, function($a, $b) {
            return (float)$a->get_price() <=> (float)$b->get_price();
        });
        
        return $products;
    }

    /**
     * Checks if user can afford all products
     *
     * This method determines if a user has enough balance to purchase all available products.
     * Used for determining header text and other UI elements that depend on user's purchasing power.
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if user can afford all products, false otherwise
     */
    public function can_user_afford_all_products(?int $user_id = null): bool {
        return $this->get_next_unaffordable_product($user_id) === null;
    }
}
