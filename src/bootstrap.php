<?php

declare(strict_types=1);

/**
 * Bootstrap for the Mistr Fachman MyCred Integration
 *
 * Modern PSR-4 autoloaded architecture replacing the old manual require system.
 * This file now only loads the autoloader and initializes the main manager.
 *
 * @package mistr-fachman
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
function mycred_debug(string $message, mixed $data = null, string $context = 'mycred', string $level = 'info'): void
{
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
        $log_message .= ' | Data: ' . (is_scalar($data) ? (string) $data : wp_json_encode($data));
    }

    error_log($log_message);
}

/**
 * Log critical architecture decisions and errors
 */
function mycred_log_architecture_event(string $event, array $data = []): void
{
    mycred_debug("ARCHITECTURE: $event", $data, 'architecture', 'info');
}

/**
 * Log performance metrics for the new architecture
 */
function mycred_log_performance(string $operation, float $start_time, array $data = []): void
{
    $duration = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
    $data['duration_ms'] = round($duration, 2);

    mycred_debug("PERFORMANCE: $operation", $data, 'performance', 'info');
}

// Enable verbose debugging conditionally
if (!defined('MYCRED_DEBUG_VERBOSE')) {
    define('MYCRED_DEBUG_VERBOSE', WP_DEBUG);
}

// Only initialize if myCred and WooCommerce are active
if (!function_exists('mycred') || !class_exists('WooCommerce')) {
    return;
}

// Load the Composer autoloader (the one and only require statement needed!)
$autoloader_path = get_stylesheet_directory() . '/lib/autoload.php';
if (file_exists($autoloader_path)) {
    require_once $autoloader_path;
} else {
    mycred_debug('Autoloader not found - run composer install', $autoloader_path, 'bootstrap', 'error');
    return;
}

// Initialize systems with service layer using dependency injection
add_action('init', function () {
    // Namespace aliases for clarity
    $ECommerceManager = \MistrFachman\MyCred\ECommerce\Manager::class;
    $ShortcodeManager = \MistrFachman\Shortcodes\ShortcodeManager::class;
    $ProductService = \MistrFachman\Services\ProductService::class;
    $UsersManager = \MistrFachman\Users\Manager::class;

    if (!class_exists($ECommerceManager)) {
        mycred_debug('Core E-Commerce Manager class not found.', null, 'bootstrap', 'error');
        return;
    }

    // === SERVICE LAYER INITIALIZATION (DI-Lite Pattern) ===
    // Create shared services that will be used across multiple domains
    
    // Core user services
    $RoleManager = \MistrFachman\Users\RoleManager::class;
    $role_manager = class_exists($RoleManager) ? new $RoleManager() : null;
    
    $UserDetectionService = \MistrFachman\Services\UserDetectionService::class;
    $user_detection_service = (class_exists($UserDetectionService) && $role_manager) 
        ? new $UserDetectionService($role_manager) 
        : null;
    
    // Future shared services will be created here:
    // $sms_service = new SmsNotificationService();
    // $leaderboard_service = new LeaderboardService();

    // 1. Initialize the Users System (first, as other domains may depend on it)
    if (class_exists($UsersManager)) {
        $users_manager = $UsersManager::get_instance();
        
        // Form configuration is now handled by RegistrationConfig class
        // No need to manually configure form ID - it's centralized in RegistrationConfig::FINAL_REGISTRATION_FORM_ID
    } else {
        mycred_debug('Users Manager class not found.', null, 'bootstrap', 'error');
    }

    // 1.5. Initialize the Realizace System with injected dependencies
    $RealizaceManager = \MistrFachman\Realizace\Manager::class;
    if (class_exists($RealizaceManager) && $user_detection_service) {
        $RealizaceManager::get_instance($user_detection_service);
        mycred_debug('Realizace Manager initialized with injected UserDetectionService', null, 'bootstrap', 'info');
    } else {
        mycred_debug('Realizace Manager class not found or UserDetectionService unavailable.', null, 'bootstrap', 'error');
    }

    // 1.6. Initialize the Faktury System with injected dependencies
    $FakturyManager = \MistrFachman\Faktury\Manager::class;
    if (class_exists($FakturyManager) && $user_detection_service) {
        $FakturyManager::get_instance($user_detection_service);
        mycred_debug('Faktury Manager initialized with injected UserDetectionService', null, 'bootstrap', 'info');
    } else {
        mycred_debug('Faktury Manager class not found or UserDetectionService unavailable.', null, 'bootstrap', 'error');
    }

    // 2. Initialize the E-Commerce System
    $ecommerce_manager = $ECommerceManager::get_instance();

    // 3. Initialize the Services Layer, injecting the dependencies they need
    $product_service = new $ProductService($ecommerce_manager);
    $user_service = isset($users_manager) ? $users_manager->get_user_service() : null;
    
    // 4. Initialize the Shortcode System, injecting all necessary services
    if (class_exists($ShortcodeManager) && $user_service) {
        $shortcode_manager = new $ShortcodeManager($ecommerce_manager, $product_service, $user_service);
        $shortcode_manager->init_shortcodes(); // Explicitly initialize
    } else {
        mycred_debug('Shortcode Manager class not found or UserService unavailable.', null, 'bootstrap', 'error');
    }

    mycred_log_architecture_event('PSR-4 Application Initialized with Service Layer', [
        'autoloader' => 'composer',
        'domains' => ['Users', 'Realizace', 'Faktury', 'ECommerce', 'Shortcodes', 'Services'],
        'pattern' => 'decoupled with service injection'
    ]);
}, 20); // Later priority to ensure plugins are loaded

// Debug helper available via URL parameter: ?debug_mycred_cart=1
// To clear cart: ?clear_mycred_cart=1
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function () {
        if (isset($_GET['debug_mycred_cart']) && current_user_can('manage_options')) {
            if (class_exists('MistrFachman\\MyCred\\ECommerce\\Manager') && function_exists('WC') && WC()->cart) {
                $manager = \MistrFachman\MyCred\ECommerce\Manager::get_instance();
                $cart_total = $manager->cart_context->get_current_cart_total();
                $available_points = $manager->get_available_points();
                $user_balance = $manager->get_user_balance();
                $is_overcommitted = $cart_total > $user_balance;
                // Shortcodes are now decoupled - we can't access them from the manager
                $shortcode_count = 'decoupled';

                echo '<div style="position:fixed;top:10px;right:10px;background:white;border:2px solid ' . ($is_overcommitted ? 'red' : 'green') . ';padding:10px;z-index:99999;font-family:monospace;font-size:12px;max-width:400px;">';
                echo '<h4>myCred Debug (PSR-4)</h4>';
                echo "<strong>Balance:</strong> $user_balance<br>";
                echo "<strong>Cart Total:</strong> $cart_total<br>";
                echo "<strong>Available:</strong> $available_points<br>";
                echo "<strong>Shortcodes:</strong> $shortcode_count<br>";
                if ($is_overcommitted) {
                    echo '<strong style="color:red;">OVERCOMMITTED</strong><br>';
                    echo '<a href="?clear_mycred_cart=1" style="background:red;color:white;padding:2px 5px;text-decoration:none;font-size:10px;">Clear</a>';
                }
                echo '</div>';
            }
        }
    });

    add_action('init', function () {
        if (isset($_GET['clear_mycred_cart']) && current_user_can('manage_options')) {
            if (function_exists('WC') && WC()->cart) {
                WC()->cart->empty_cart();
                wp_redirect(remove_query_arg('clear_mycred_cart'));
                exit;
            }
        }
    });
}
