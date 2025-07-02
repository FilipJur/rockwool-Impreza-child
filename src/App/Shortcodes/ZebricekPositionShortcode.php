<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * Žebříček Position Shortcode Component
 *
 * Displays current user's position in the leaderboard and their annual points.
 * Shows different content based on user access level and login status.
 *
 * Usage: [zebricek_position]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZebricekPositionShortcode extends ShortcodeBase
{
    protected array $default_attributes = [
        'show_annual_points' => 'true',
        'current_year' => '',
        'class' => ''
    ];

    private const CACHE_KEY_PREFIX = 'zebricek_position_';
    private const CACHE_DURATION = 600; // 10 minutes

    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
    }

    public function get_tag(): string
    {
        return 'zebricek_position';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // $content parameter unused for this shortcode type
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            $user_status = 'logged_out';
            $position_data = null;
        } else {
            $status = $this->user_service->get_user_registration_status($user_id);
            
            if ($status !== 'full_member' && $status !== 'other') {
                $user_status = 'pending';
                $position_data = null;
            } else {
                $user_status = 'full_member';
                $current_year = !empty($attributes['current_year']) ? $attributes['current_year'] : date('Y');
                $position_data = $this->get_cached_position_data($user_id, $current_year);
            }
        }

        // Prepare template data
        $template_data = [
            'position_data' => $position_data,
            'attributes' => $attributes,
            'wrapper_classes' => $this->get_wrapper_classes($attributes),
            'user_status' => $user_status,
            'renderer' => $this
        ];

        return $this->load_template('zebricek/position.php', $template_data);
    }

    /**
     * Get position data with caching
     */
    private function get_cached_position_data(int $user_id, string $year): array
    {
        $cache_key = self::CACHE_KEY_PREFIX . $user_id . '_' . $year;
        
        // Try to get from cache
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false && is_array($cached_data)) {
            return $cached_data;
        }

        // Generate fresh data
        $position_data = $this->get_user_position_data($user_id, $year);
        
        // Cache the result
        set_transient($cache_key, $position_data, self::CACHE_DURATION);
        
        return $position_data;
    }

    /**
     * Get user's position and annual points data
     */
    private function get_user_position_data(int $user_id, string $year): array
    {
        // Get user's position using myCred leaderboard position shortcode
        $position = $this->get_user_leaderboard_position($user_id);
        
        // Get annual points (for current year)
        $annual_points = $this->get_user_annual_points($user_id, $year);
        
        return [
            'position' => $position,
            'position_formatted' => "{$position}. pozice",
            'annual_points' => $annual_points,
            'annual_points_formatted' => number_format($annual_points, 0, ',', ' ') . ' b.',
            'year' => $year
        ];
    }

    /**
     * Get user's current position in leaderboard using myCred
     */
    private function get_user_leaderboard_position(int $user_id): int
    {
        // Calculate position based on current year points
        return $this->calculate_user_position_for_year($user_id, date('Y'));
    }

    /**
     * Calculate user's position based on current year points
     */
    private function calculate_user_position_for_year(int $user_id, string $year): int
    {
        global $wpdb;
        
        // Get user's current year points
        $user_points = $this->get_user_annual_points($user_id, $year);
        
        $this->debug_log('Calculating position', [
            'user_id' => $user_id,
            'year' => $year,
            'user_points' => $user_points
        ]);
        
        if ($user_points === 0) {
            // User has no points this year
            $this->debug_log('User has no points this year');
            return 999; // Or some high number to indicate they're not ranked
        }
        
        // Build the myCred log table name
        $log_table = $wpdb->prefix . 'mycred_log';
        
        // Count users with more points than current user in current year
        // Convert to Unix timestamps
        $year_start = strtotime($year . '-01-01 00:00:00');
        $year_end = strtotime(($year + 1) . '-01-01 00:00:00');
        
        // Filter by achievement-related transactions only
        $achievement_refs = [
            'approval_of_realization',
            'revoke_realization_points', 
            'approval_of_invoice',
            'revoke_invoice_points'
        ];
        $ref_placeholders = implode(',', array_fill(0, count($achievement_refs), '%s'));
        
        $query = $wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) + 1 as position
            FROM (
                SELECT user_id, SUM(creds) as total_points
                FROM {$log_table}
                WHERE time >= %d
                    AND time < %d
                    AND ref IN ({$ref_placeholders})
                GROUP BY user_id
                HAVING total_points > %d
            ) as better_users
        ", array_merge([$year_start, $year_end], $achievement_refs, [$user_points]));
        
        $position = $wpdb->get_var($query);
        
        $this->debug_log('Position calculated', [
            'position' => $position,
            'query_error' => $wpdb->last_error
        ]);
        
        return max(1, (int)$position);
    }

    /**
     * Get user's total points using myCred function
     */
    private function get_user_total_points(int $user_id): int
    {
        if (function_exists('mycred_get_users_balance')) {
            return (int)mycred_get_users_balance($user_id, 'mycred_default');
        }
        
        return (int)get_user_meta($user_id, 'mycred_default', true);
    }

    /**
     * Get user's points earned in specific year using myCred log
     */
    private function get_user_annual_points(int $user_id, string $year): int
    {
        global $wpdb;
        
        // Query mycred_log for points earned in specific year
        $log_table = "{$wpdb->prefix}mycred_log";
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$log_table'") != $log_table) {
            // Table doesn't exist, return total points as fallback
            return $this->get_user_total_points($user_id);
        }
        
        // Convert to Unix timestamps
        $year_start = strtotime($year . '-01-01 00:00:00');
        $year_end = strtotime(($year + 1) . '-01-01 00:00:00');
        
        // Filter by achievement-related transactions only
        $achievement_refs = [
            'approval_of_realization',
            'revoke_realization_points', 
            'approval_of_invoice',
            'revoke_invoice_points'
        ];
        $ref_placeholders = implode(',', array_fill(0, count($achievement_refs), '%s'));
        
        $annual_points_query = $wpdb->prepare("
            SELECT COALESCE(SUM(creds), 0) as annual_points
            FROM {$log_table}
            WHERE user_id = %d 
            AND time >= %d
            AND time < %d
            AND ref IN ({$ref_placeholders})
        ", array_merge([$user_id, $year_start, $year_end], $achievement_refs));
        
        $annual_points = $wpdb->get_var($annual_points_query);
        
        return max(0, (int)$annual_points);
    }

    /**
     * Load template with data
     * Following the pattern from admin templates
     */
    public function load_template(string $template_name, array $data = []): string
    {
        $template_path = get_stylesheet_directory() . '/templates/' . $template_name;
        
        if (!file_exists($template_path)) {
            if (current_user_can('manage_options')) {
                return $this->render_error("Template not found: {$template_name}");
            }
            return '<div class="zebricek-error">Došlo k chybě při načítání obsahu.</div>';
        }

        // Extract data to variables for template
        extract($data, EXTR_SKIP);
        
        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Clear position cache for specific user
     */
    public static function clear_user_cache(int $user_id): void
    {
        global $wpdb;
        
        // Delete position transients for this user
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_KEY_PREFIX . $user_id . '_%'
        ));
        
        // Also clear timeout transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . self::CACHE_KEY_PREFIX . $user_id . '_%'
        ));
    }

    /**
     * Override sanitize_attributes with enhanced validation
     */
    protected function sanitize_attributes(array $attributes): array
    {
        $sanitized = parent::sanitize_attributes($attributes);
        
        // Sanitize position-specific attributes
        $sanitized['show_annual_points'] = in_array($sanitized['show_annual_points'], ['true', 'false'], true) ? $sanitized['show_annual_points'] : 'true';
        $sanitized['current_year'] = !empty($sanitized['current_year']) ? sanitize_text_field($sanitized['current_year']) : date('Y');
        
        return $sanitized;
    }

    /**
     * Debug logging specifically for zebricek
     */
    private function debug_log(string $message, $data = null): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_path = get_stylesheet_directory() . '/debug_zebricek.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] POSITION: $message";
        
        if ($data !== null) {
            $log_entry .= "\n" . print_r($data, true);
        }
        
        file_put_contents($log_path, $log_entry . "\n\n", FILE_APPEND);
    }
}