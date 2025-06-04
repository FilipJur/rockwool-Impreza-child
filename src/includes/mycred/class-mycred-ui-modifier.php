<?php
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
    public function init_hooks() {
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'modify_add_to_cart_button'], 20, 3);
    }
    
    /**
     * Modify "Add to Cart" button text and styling when not affordable
     *
     * @param string $html_link Original button HTML
     * @param WC_Product $product Product object
     * @param array $args Button arguments
     * @return string Modified button HTML
     */
    public function modify_add_to_cart_button($html_link, $product, $args) {
        // Safety check for valid product object
        if (!$product instanceof WC_Product) {
            return $html_link;
        }
        
        // If product is not purchasable (determined by our purchasability filter)
        if (!$product->is_purchasable()) {
            return $this->get_disabled_button($product, $args);
        }
        
        return $html_link;
    }
    
    /**
     * Generate disabled button HTML for unaffordable products
     *
     * @param WC_Product $product Product object
     * @param array $args Button arguments
     * @return string Disabled button HTML
     */
    private function get_disabled_button($product, $args) {
        // Only modify simple, external, or grouped products
        // Variable products typically have "Select options" which we leave unchanged
        if (!$this->should_modify_button($product)) {
            return '';
        }
        
        $message = $this->get_disabled_button_text();
        $padding = $this->get_button_padding($args);
        
        return sprintf(
            '<span class="button disabled mycred-insufficient-points" style="%s">%s</span>',
            $this->get_disabled_button_styles($padding),
            esc_html($message)
        );
    }
    
    /**
     * Determine if we should modify this product's button
     *
     * @param WC_Product $product Product object
     * @return bool True if button should be modified
     */
    private function should_modify_button($product) {
        return $product->is_type('simple') 
            || $product->is_type('external') 
            || $product->is_type('grouped');
    }
    
    /**
     * Get text for disabled button
     *
     * @return string Button text
     */
    private function get_disabled_button_text() {
        return __('Not enough points (inc. cart)', 'woocommerce');
    }
    
    /**
     * Get button padding from arguments
     *
     * @param array $args Button arguments
     * @return string Padding value
     */
    private function get_button_padding($args) {
        return isset($args['attributes']['style']) 
            ? esc_attr($args['attributes']['style']) 
            : '0.618em 1em';
    }
    
    /**
     * Get CSS styles for disabled button
     *
     * @param string $padding Button padding
     * @return string CSS styles
     */
    private function get_disabled_button_styles($padding) {
        return implode(' ', [
            'background-color: #e0e0e0 !important;',
            'color: #888888 !important;',
            'cursor: not-allowed !important;',
            'padding: ' . $padding . ' !important;',
            'display: inline-block;',
            'text-align: center;',
            'border: none;',
            'text-decoration: none;'
        ]);
    }
    
    /**
     * Add custom CSS for disabled buttons
     *
     * This method can be called to add additional styling if needed.
     */
    public function add_custom_styles() {
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
     *
     * @param WC_Product $product Product to check
     * @return string Affordability message
     */
    public function get_affordability_message($product) {
        if (!$product instanceof WC_Product) {
            return '';
        }
        
        $manager = MyCred_Manager::get_instance();
        $calculator = $manager->get_cart_calculator();
        
        $user_balance = $calculator->get_user_balance();
        $product_cost = $calculator->get_product_point_cost($product);
        $cart_total = $calculator->get_cart_points_total();
        $potential_total = $calculator->get_potential_total_cost($product);
        
        if (is_null($product_cost)) {
            return '';
        }
        
        if ($potential_total > $user_balance) {
            $needed = $potential_total - $user_balance;
            return sprintf(
                __('You need %d more points to purchase this item (including cart contents).', 'woocommerce'),
                ceil($needed)
            );
        }
        
        return '';
    }
}
