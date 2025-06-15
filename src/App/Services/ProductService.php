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
     * @return \WC_Product[] The filtered list of WC_Product objects.
     */
    public function get_products_filtered_by_balance(string $filter_type, array $query_args = []): array {
        $all_products = $this->get_products($query_args);

        if ($filter_type === 'all' || empty($all_products)) {
            return $all_products;
        }

        $filtered_products = [];
        foreach ($all_products as $product) {
            if (!$product instanceof \WC_Product) continue;

            // Use the injected manager to access the core affordability logic.
            $is_affordable = $this->ecommerce_manager->can_afford_product($product);

            if (($filter_type === 'affordable' && $is_affordable) || ($filter_type === 'unavailable' && !$is_affordable)) {
                $filtered_products[] = $product;
            }
        }

        return $filtered_products;
    }
}
