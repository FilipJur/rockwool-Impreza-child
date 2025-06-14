<?php

declare(strict_types=1);

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

/**
 * Smart debug helper for myCred integration with rate limiting
 *
 * @param string $message Debug message
 * @param mixed $data Optional data to log
 * @param string $context Context identifier
 * @param string $level Debug level: 'error', 'warning', 'info', 'verbose'
 */
function mycred_debug(string $message, mixed $data = null, string $context = 'mycred', string $level = 'info'): void {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    // Rate limiting for verbose logs - only log once per 30 seconds for same message
    if ($level === 'verbose') {
        static $rate_limit_cache = [];
        $cache_key = md5($context . $message);
        $now = time();
        
        if (isset($rate_limit_cache[$cache_key]) && ($now - $rate_limit_cache[$cache_key]) < 30) {
            return; // Skip this log entry
        }
        
        $rate_limit_cache[$cache_key] = $now;
        
        // Clean old entries every 100 calls
        if (count($rate_limit_cache) > 100) {
            $rate_limit_cache = array_filter($rate_limit_cache, fn($time) => ($now - $time) < 300);
        }
    }
    
    // Only log errors and warnings by default, unless explicitly enabled
    if ($level === 'verbose' && !defined('MYCRED_DEBUG_VERBOSE')) {
        return;
    }
    
    $log_message = sprintf('[%s:%s] %s', strtoupper($context), strtoupper($level), $message);
    
    if ($data !== null) {
        $log_message .= ' | Data: ' . (is_scalar($data) ? (string)$data : wp_json_encode($data));
    }
    
    error_log($log_message);
}

/**
 * To enable verbose debugging (more detailed logs), add this to wp-config.php:
 * define('MYCRED_DEBUG_VERBOSE', true);
 * 
 * Note: Verbose logs are rate-limited to prevent spam.
 */

// Temporarily enable verbose debugging for troubleshooting
define('MYCRED_DEBUG_VERBOSE', true);

// Only initialize if myCred and WooCommerce are active
if (!function_exists('mycred') || !class_exists('WooCommerce')) {
    return;
}

// Include required classes
require_once get_stylesheet_directory() . '/src/includes/mycred/class-mycred-manager.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/class-mycred-cart-calculator.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/class-mycred-purchasability.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/class-mycred-ui-modifier.php';

// Initialize the integration
add_action('init', function() {
    if (class_exists('MyCred_Manager')) {
        MyCred_Manager::get_instance();
    }
}, 20); // Later priority to ensure plugins are loaded
