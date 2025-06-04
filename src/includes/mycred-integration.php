<?php
/**
 * myCred WooCommerce Integration Bootstrap
 *
 * This file initializes the myCred integration for WooCommerce,
 * handling point-based purchasing with cart awareness.
 *
 * @package impreza-child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only initialize if myCred and WooCommerce are active
if (!function_exists('mycred') || !class_exists('WooCommerce')) {
    return;
}

// Include required classes
require_once get_stylesheet_directory() . '/src/includes/mycred/class-mycred-manager.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/class-mycred-cart-calculator.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/class-mycred-purchasability.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/class-mycred-ui-modifier.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/class-mycred-ajax-handler.php';

// Initialize the integration
add_action('init', function() {
    if (class_exists('MyCred_Manager')) {
        MyCred_Manager::get_instance();
    }
}, 20); // Later priority to ensure plugins are loaded
