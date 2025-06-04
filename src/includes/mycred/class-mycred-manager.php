<?php
/**
 * MyCred Manager Class
 *
 * Main orchestrator for myCred WooCommerce integration.
 * Handles initialization and coordination between components.
 *
 * @package impreza-child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MyCred_Manager {
    
    /**
     * Single instance of this class
     *
     * @var MyCred_Manager|null
     */
    private static $instance = null;
    
    /**
     * Cart calculator instance
     *
     * @var MyCred_Cart_Calculator|null
     */
    private $cart_calculator;
    
    /**
     * Purchasability checker instance
     *
     * @var MyCred_Purchasability|null
     */
    private $purchasability;
    
    /**
     * UI modifier instance
     *
     * @var MyCred_UI_Modifier|null
     */
    private $ui_modifier;
    
    /**
     * AJAX handler instance
     *
     * @var MyCred_Ajax_Handler|null
     */
    private $ajax_handler;
    
    /**
     * Get singleton instance
     *
     * @return MyCred_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the integration
     */
    private function init() {
        // Initialize components
        $this->cart_calculator = new MyCred_Cart_Calculator();
        $this->purchasability = new MyCred_Purchasability($this->cart_calculator);
        $this->ui_modifier = new MyCred_UI_Modifier();
        $this->ajax_handler = new MyCred_Ajax_Handler($this->cart_calculator);
        
        // Hook into WordPress
        $this->setup_hooks();
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Let purchasability component handle its own hooks
        $this->purchasability->init_hooks();
        
        // Let UI modifier handle its own hooks
        $this->ui_modifier->init_hooks();
        
        // Let AJAX handler handle its own hooks
        $this->ajax_handler->init_hooks();
    }
    
    /**
     * Get the cart calculator instance
     *
     * @return MyCred_Cart_Calculator
     */
    public function get_cart_calculator() {
        return $this->cart_calculator;
    }
    
    /**
     * Get the purchasability checker instance
     *
     * @return MyCred_Purchasability
     */
    public function get_purchasability() {
        return $this->purchasability;
    }
    
    /**
     * Get the UI modifier instance
     *
     * @return MyCred_UI_Modifier
     */
    public function get_ui_modifier() {
        return $this->ui_modifier;
    }
    
    /**
     * Get the AJAX handler instance
     *
     * @return MyCred_Ajax_Handler
     */
    public function get_ajax_handler() {
        return $this->ajax_handler;
    }
    
    /**
     * Get myCred point type for WooCommerce
     * 
     * Handles fallback logic for determining the correct point type.
     *
     * @return string Point type key
     */
    public function get_woo_point_type() {
        // Try to use preferred myCred function if it exists
        if (function_exists('mycred_get_woo_point_type')) {
            $point_type = mycred_get_woo_point_type();
            if (!empty($point_type)) {
                return $point_type;
            }
        }
        
        // Fallback: direct reading of myCred gateway settings
        $gateway_settings = get_option('woocommerce_mycred_settings');
        return isset($gateway_settings['point_type']) 
            ? $gateway_settings['point_type'] 
            : (defined('MYCRED_DEFAULT_TYPE_KEY') ? MYCRED_DEFAULT_TYPE_KEY : 'mycred_default');
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {}
}
