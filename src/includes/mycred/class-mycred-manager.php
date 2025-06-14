<?php

declare(strict_types=1);

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
    
    private static ?self $instance = null;
    private static ?string $cached_point_type = null;
    
    public readonly MyCred_Cart_Calculator $cart_calculator;
    public readonly MyCred_Purchasability $purchasability;
    public readonly MyCred_UI_Modifier $ui_modifier;
    
    
    /**
     * Get singleton instance
     */
    public static function get_instance(): self {
        return self::$instance ??= new self();
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->cart_calculator = new MyCred_Cart_Calculator();
        $this->purchasability = new MyCred_Purchasability($this->cart_calculator);
        $this->ui_modifier = new MyCred_UI_Modifier();
        
        $this->setup_hooks();
        
        mycred_debug('MyCred integration initialized', null, 'manager', 'info');
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks(): void {
        $this->purchasability->init_hooks();
        $this->ui_modifier->init_hooks();
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
            default => 'mycred_default'
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
