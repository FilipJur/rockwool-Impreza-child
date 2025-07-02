<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * Žebříček (Leaderboard) Shortcode Component
 *
 * Dynamic leaderboard displaying top users ranked by lifetime points earned.
 * Shows different content based on user access level:
 * - Pending users: Names only (points hidden)
 * - Full members: Names, companies, and points
 *
 * Usage: [zebricek_leaderboard limit="30" offset="0"]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZebricekLeaderboardShortcode extends ShortcodeBase
{
    protected array $default_attributes = [
        'limit' => '30',
        'offset' => '0',
        'show_numbers' => 'true',
        'show_pagination' => 'true',
        'class' => ''
    ];

    private const CACHE_KEY_PREFIX = 'zebricek_leaderboard_';
    private const CACHE_DURATION = 900; // 15 minutes

    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
    }

    public function get_tag(): string
    {
        return 'zebricek_leaderboard';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // $content parameter unused for this shortcode type
        $user_id = get_current_user_id();
        $status = $this->user_service->get_user_registration_status($user_id);
        
        // Determine access level
        $is_full_member = $status === 'full_member' || $status === 'other';
        
        // Get leaderboard data with caching
        $leaderboard_data = $this->get_cached_leaderboard_data($attributes);
        
        // Prepare template data
        $template_data = [
            'leaderboard_data' => $leaderboard_data,
            'attributes' => $attributes,
            'is_full_member' => $is_full_member,
            'wrapper_classes' => $this->get_wrapper_classes($attributes),
            'renderer' => $this
        ];

        return $this->load_template('zebricek/leaderboard.php', $template_data);
    }

    /**
     * Get leaderboard data with caching
     */
    private function get_cached_leaderboard_data(array $attributes): array
    {
        $cache_key = self::CACHE_KEY_PREFIX . md5(serialize($attributes));
        
        // Try to get from cache
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false && is_array($cached_data)) {
            $this->debug_log('Using cached leaderboard data', ['cache_key' => $cache_key]);
            return $cached_data;
        }

        // Generate fresh data
        $this->debug_log('Generating fresh leaderboard data', $attributes);
        $leaderboard_data = $this->get_leaderboard_data_current_year($attributes);
        
        // Cache the result
        set_transient($cache_key, $leaderboard_data, self::CACHE_DURATION);
        
        return $leaderboard_data;
    }

    /**
     * Get leaderboard data for current year from myCred log
     */
    private function get_leaderboard_data_current_year(array $attributes): array
    {
        global $wpdb;
        
        $users = [];
        $limit = (int)$attributes['limit'];
        $offset = (int)$attributes['offset'];
        $current_year = date('Y');
        
        // Find the actual myCred log table (case-insensitive)
        $log_table = $this->find_mycred_log_table();
        
        if (!$log_table) {
            $this->debug_log('myCred log table not found - trying native shortcode fallback');
            return $this->get_leaderboard_via_native_shortcode($attributes);
        }
        
        // Query for total points earned in current year
        // Convert to Unix timestamps since myCred stores time as BIGINT
        $year_start = strtotime($current_year . '-01-01 00:00:00');
        $year_end = strtotime(($current_year + 1) . '-01-01 00:00:00');
        
        // Filter by achievement-related transactions only
        $achievement_refs = [
            'approval_of_realization',
            'revoke_realization_points', 
            'approval_of_invoice',
            'revoke_invoice_points'
        ];
        $ref_placeholders = implode(',', array_fill(0, count($achievement_refs), '%s'));
        
        $query = $wpdb->prepare("
            SELECT 
                log.user_id,
                u.display_name,
                SUM(log.creds) as total_points
            FROM {$log_table} log
            INNER JOIN {$wpdb->users} u ON log.user_id = u.ID
            WHERE log.time >= %d
                AND log.time < %d
                AND log.ref IN ({$ref_placeholders})
            GROUP BY log.user_id
            HAVING total_points > 0
            ORDER BY total_points DESC
            LIMIT %d OFFSET %d
        ", array_merge([$year_start, $year_end], $achievement_refs, [$limit, $offset]));
        
        $this->debug_log('Executing achievement-based leaderboard query', [
            'year' => $current_year,
            'year_start' => $year_start . ' (' . date('Y-m-d H:i:s', $year_start) . ')',
            'year_end' => $year_end . ' (' . date('Y-m-d H:i:s', $year_end) . ')',
            'achievement_refs' => $achievement_refs,
            'limit' => $limit,
            'offset' => $offset,
            'table' => $log_table
        ]);
        
        $results = $wpdb->get_results($query);
        
        if ($wpdb->last_error) {
            $this->debug_log('Database error', ['error' => $wpdb->last_error, 'query' => $query]);
            return [];
        }
        
        if ($results) {
            $this->debug_log('Found users', ['count' => count($results)]);
            
            foreach ($results as $user_data) {
                $user_id = (int)$user_data->user_id;
                $points = (int)$user_data->total_points;
                
                $users[] = [
                    'user_id' => $user_id,
                    'display_name' => $user_data->display_name,
                    'first_name' => get_user_meta($user_id, 'first_name', true) ?: '',
                    'last_name' => get_user_meta($user_id, 'last_name', true) ?: '',
                    'company' => get_user_meta($user_id, 'billing_company', true) ?: 'Samostatný řemeslník',
                    'points' => $points,
                    'formatted_points' => number_format($points, 0, ',', ' ') . ' b.'
                ];
            }
        } else {
            $this->debug_log('No users found with points in current year');
        }

        return $users;
    }

    /**
     * Find the actual myCred log table name (handles case sensitivity)
     */
    private function find_mycred_log_table(): ?string
    {
        global $wpdb;
        
        // Check common variations
        $variations = [
            $wpdb->prefix . 'mycred_log',
            $wpdb->prefix . 'myCRED_log',
            $wpdb->prefix . 'mycred_Log',
            $wpdb->prefix . 'MYCRED_LOG'
        ];
        
        foreach ($variations as $table_name) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if ($exists) {
                $this->debug_log('Found myCred log table', ['table' => $table_name]);
                return $table_name;
            }
        }
        
        // If not found, search all tables
        $tables = $wpdb->get_col("SHOW TABLES");
        foreach ($tables as $table) {
            if (stripos($table, 'mycred') !== false && stripos($table, 'log') !== false) {
                $this->debug_log('Found myCred log table via search', ['table' => $table]);
                return $table;
            }
        }
        
        return null;
    }
    
    /**
     * Get leaderboard data using native myCred shortcode
     */
    private function get_leaderboard_via_native_shortcode(array $attributes): array
    {
        $current_year = date('Y');
        $timeframe = $current_year . '-01-01,00:00:00';
        
        // Build shortcode with custom template to extract data
        $template = '%position%|%user_id%|%display_name%|%cred%|END';
        $shortcode = sprintf(
            '[mycred_leaderboard number="%d" offset="%d" based_on="total" timeframe="%s" template="%s" exclude_zero="1"]',
            (int)$attributes['limit'],
            (int)$attributes['offset'],
            $timeframe,
            $template
        );
        
        $this->debug_log('Trying native myCred shortcode', ['shortcode' => $shortcode]);
        
        $output = do_shortcode($shortcode);
        
        if (empty($output)) {
            $this->debug_log('No output from native shortcode');
            return [];
        }
        
        // Parse the output
        $users = [];
        $entries = explode('END', $output);
        
        foreach ($entries as $entry) {
            $entry = trim($entry);
            if (empty($entry)) continue;
            
            $parts = explode('|', $entry);
            if (count($parts) >= 4) {
                $user_id = (int)$parts[1];
                $points = (int)$parts[3];
                
                $users[] = [
                    'user_id' => $user_id,
                    'display_name' => $parts[2],
                    'first_name' => get_user_meta($user_id, 'first_name', true) ?: '',
                    'last_name' => get_user_meta($user_id, 'last_name', true) ?: '',
                    'company' => get_user_meta($user_id, 'billing_company', true) ?: 'Samostatný řemeslník',
                    'points' => $points,
                    'formatted_points' => number_format($points, 0, ',', ' ') . ' b.'
                ];
            }
        }
        
        $this->debug_log('Parsed users from native shortcode', ['count' => count($users)]);
        return $users;
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
        $log_entry = "[$timestamp] $message";
        
        if ($data !== null) {
            $log_entry .= "\n" . print_r($data, true);
        }
        
        file_put_contents($log_path, $log_entry . "\n\n", FILE_APPEND);
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
     * Clear cache when needed (can be called from other parts of the system)
     */
    public static function clear_cache(): void
    {
        global $wpdb;
        
        // Delete all zebricek transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_KEY_PREFIX . '%'
        ));
        
        // Also clear timeout transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . self::CACHE_KEY_PREFIX . '%'
        ));
    }

    /**
     * Override sanitize_attributes with enhanced validation
     */
    protected function sanitize_attributes(array $attributes): array
    {
        $sanitized = parent::sanitize_attributes($attributes);
        
        // Enhanced validation for leaderboard-specific attributes
        $sanitized['limit'] = max(1, min(100, absint($sanitized['limit']))); // 1-100 range
        $sanitized['offset'] = max(0, absint($sanitized['offset']));
        $sanitized['show_numbers'] = in_array($sanitized['show_numbers'], ['true', 'false'], true) ? $sanitized['show_numbers'] : 'true';
        $sanitized['show_pagination'] = in_array($sanitized['show_pagination'], ['true', 'false'], true) ? $sanitized['show_pagination'] : 'true';
        
        return $sanitized;
    }
}

// Clear leaderboard cache when posts are approved/disapproved
add_action('transition_post_status', function($new_status, $old_status, $post) {
    // Only clear cache for realizace and faktury posts
    if (in_array($post->post_type, ['realization', 'invoice'])) {
        ZebricekLeaderboardShortcode::clear_cache();
        
        // Also clear position cache for affected user
        $user_id = (int)get_post_meta($post->ID, '_user_id', true);
        if ($user_id > 0 && class_exists('MistrFachman\\Shortcodes\\ZebricekPositionShortcode')) {
            ZebricekPositionShortcode::clear_user_cache($user_id);
        }
    }
}, 10, 3);

// Note: We only use post status transition hook for cache clearing
// to avoid interfering with WooCommerce/myCred payment processing