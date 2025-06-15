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
 * To enable verbose debugging (more detailed logs), add this to wp-config.php:
 * define('MYCRED_DEBUG_VERBOSE', true);
 *
 * Note: Verbose logs are rate-limited to prevent spam.
 */

// Enable verbose debugging conditionally
if (!defined('MYCRED_DEBUG_VERBOSE')) {
	define('MYCRED_DEBUG_VERBOSE', WP_DEBUG);
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

// Only initialize if myCred and WooCommerce are active
if (!function_exists('mycred') || !class_exists('WooCommerce')) {
	return;
}

// Include required classes (in dependency order)
require_once get_stylesheet_directory() . '/src/includes/mycred/pricing/class-cart-context.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/pricing/class-balance-calculator.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/pricing/class-purchasability.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/pricing/class-ui-modifier.php';
require_once get_stylesheet_directory() . '/src/includes/mycred/pricing/class-manager.php';


// Initialize the integration
add_action('init', function () {
	if (class_exists('MyCred_Pricing_Manager')) {
		MyCred_Pricing_Manager::get_instance();
	}
}, 20); // Later priority to ensure plugins are loaded

// Test hook removed - issue identified

// Debug helper available via URL parameter: ?debug_mycred_cart=1
// To clear cart: ?clear_mycred_cart=1
if (defined('WP_DEBUG') && WP_DEBUG) {
	add_action('wp_footer', function () {
		if (isset($_GET['debug_mycred_cart']) && current_user_can('manage_options')) {
			if (class_exists('MyCred_Pricing_Manager') && function_exists('WC') && WC()->cart) {
				$manager = MyCred_Pricing_Manager::get_instance();
				$cart_total = $manager->cart_context->get_current_cart_total();
				$available_points = $manager->get_available_points();
				$user_balance = $manager->get_user_balance();
				$is_overcommitted = $cart_total > $user_balance;

				echo '<div style="position:fixed;top:10px;right:10px;background:white;border:2px solid ' . ($is_overcommitted ? 'red' : 'green') . ';padding:10px;z-index:99999;font-family:monospace;font-size:12px;max-width:400px;">';
				echo '<h4>myCred Debug</h4>';
				echo "<strong>Balance:</strong> $user_balance<br>";
				echo "<strong>Cart Total:</strong> $cart_total<br>";
				echo "<strong>Available:</strong> $available_points<br>";
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
