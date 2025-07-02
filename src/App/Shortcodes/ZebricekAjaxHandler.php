<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * Žebříček AJAX Handler
 *
 * Handles AJAX requests for leaderboard pagination functionality.
 * Provides secure endpoints for loading more users.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZebricekAjaxHandler
{
    private Manager $ecommerce_manager;
    private ProductService $product_service;
    private UserService $user_service;
    private ZebricekLeaderboardShortcode $leaderboard_shortcode;

    public function __construct(
        Manager $ecommerce_manager, 
        ProductService $product_service, 
        UserService $user_service
    ) {
        $this->ecommerce_manager = $ecommerce_manager;
        $this->product_service = $product_service;
        $this->user_service = $user_service;
        
        // Create shortcode instance for rendering
        $this->leaderboard_shortcode = new ZebricekLeaderboardShortcode(
            $ecommerce_manager, 
            $product_service, 
            $user_service
        );
    }

    /**
     * Register AJAX hooks
     */
    public function register_hooks(): void
    {
        add_action('wp_ajax_zebricek_load_more', [$this, 'handle_load_more']);
        add_action('wp_ajax_nopriv_zebricek_load_more', [$this, 'handle_load_more']);
        
        // Enqueue scripts and localize AJAX data
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue scripts and localize AJAX data
     */
    public function enqueue_scripts(): void
    {
        // Only enqueue on pages that might have zebricek shortcodes
        if (!$this->should_enqueue_scripts()) {
            return;
        }

        wp_localize_script('mistr-fachman-main', 'zebricekAjax', [
            'nonce' => wp_create_nonce('zebricek_ajax_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    /**
     * Check if scripts should be enqueued on current page
     */
    private function should_enqueue_scripts(): bool
    {
        global $post;
        
        // Check if current page/post contains zebricek shortcodes
        if ($post && has_shortcode($post->post_content, 'zebricek_leaderboard')) {
            return true;
        }
        
        // Check if we're on a known zebricek page
        $zebricek_pages = ['zebricek', 'leaderboard'];
        foreach ($zebricek_pages as $page) {
            if (is_page($page)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Handle AJAX load more request
     */
    public function handle_load_more(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zebricek_ajax_nonce')) {
                wp_die('Security check failed', 'Security Error', ['response' => 403]);
            }

            // Sanitize and validate input
            $offset = max(0, absint($_POST['offset'] ?? 0));
            $limit = max(1, min(50, absint($_POST['limit'] ?? 30))); // Max 50 at once
            
            // Get user access level
            $user_id = get_current_user_id();
            $status = $this->user_service->get_user_registration_status($user_id);
            $is_full_member = $status === 'full_member' || $status === 'other';
            
            // Get leaderboard data directly
            $leaderboard_data = $this->get_ajax_leaderboard_data($limit, $offset);
            
            // Render rows using templates
            $user_rows_html = $this->render_user_rows($leaderboard_data, $offset, $is_full_member);
            
            // Check if there are more users available
            $has_more = $this->check_has_more_users($offset + $limit);

            wp_send_json_success([
                'html' => $user_rows_html,
                'has_more' => $has_more,
                'offset' => $offset + $limit
            ]);

        } catch (\Exception $e) {
            mycred_debug('Zebricek AJAX error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'zebricek_ajax', 'error');

            wp_send_json_error([
                'message' => 'Nepodařilo se načíst další uživatele.'
            ]);
        }
    }

    /**
     * Get leaderboard data for AJAX (similar to shortcode but without caching)
     */
    private function get_ajax_leaderboard_data(int $limit, int $offset): array
    {
        return $this->get_leaderboard_data_current_year($limit, $offset);
    }

    /**
     * Get leaderboard data for current year from myCred log
     */
    private function get_leaderboard_data_current_year(int $limit, int $offset): array
    {
        global $wpdb;
        
        $users = [];
        $current_year = date('Y');
        
        // Find the actual myCred log table (case-insensitive)
        $log_table = $this->find_mycred_log_table();
        
        if (!$log_table) {
            $this->debug_log('myCred log table not found', ['tried_prefix' => $wpdb->prefix]);
            return [];
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
        
        $this->debug_log('AJAX: Executing achievement-based leaderboard query', [
            'year' => $current_year,
            'achievement_refs' => $achievement_refs,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        $results = $wpdb->get_results($query);
        
        if ($wpdb->last_error) {
            $this->debug_log('AJAX: Database error', ['error' => $wpdb->last_error, 'query' => $query]);
            return [];
        }
        
        if ($results) {
            $this->debug_log('AJAX: Found users', ['count' => count($results)]);
            
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
            $this->debug_log('AJAX: No users found with points in current year');
        }

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
     * Render user rows using templates
     */
    private function render_user_rows(array $leaderboard_data, int $offset, bool $is_full_member): string
    {
        if (empty($leaderboard_data)) {
            return '';
        }

        $rows_html = '';
        
        foreach ($leaderboard_data as $index => $user_data) {
            $position = $offset + $index + 1;
            $rows_html .= $this->load_template('zebricek/leaderboard-row.php', [
                'user_data' => $user_data,
                'position' => $position,
                'is_full_member' => $is_full_member
            ]);
        }
        
        return $rows_html;
    }

    /**
     * Load template with data
     */
    private function load_template(string $template_name, array $data = []): string
    {
        $template_path = get_stylesheet_directory() . '/templates/' . $template_name;
        
        if (!file_exists($template_path)) {
            return '';
        }

        // Extract data to variables for template
        extract($data, EXTR_SKIP);
        
        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Check if there are more users available beyond the given offset
     */
    private function check_has_more_users(int $offset): bool
    {
        global $wpdb;
        $current_year = date('Y');
        $log_table = $this->find_mycred_log_table();
        
        if (!$log_table) {
            return false;
        }
        
        // Convert to Unix timestamps
        $year_start = strtotime($current_year . '-01-01 00:00:00');
        $year_end = strtotime(($current_year + 1) . '-01-01 00:00:00');
        
        // Filter by achievement refs for consistency
        $achievement_refs = [
            'approval_of_realization',
            'revoke_realization_points', 
            'approval_of_invoice',
            'revoke_invoice_points'
        ];
        $ref_placeholders = implode(',', array_fill(0, count($achievement_refs), '%s'));
        
        // Simpler query to check if there are more users
        $query = $wpdb->prepare("
            SELECT COUNT(DISTINCT user_id)
            FROM (
                SELECT user_id
                FROM {$log_table}
                WHERE time >= %d
                    AND time < %d
                    AND ref IN ({$ref_placeholders})
                GROUP BY user_id
                HAVING SUM(creds) > 0
                ORDER BY SUM(creds) DESC
                LIMIT %d, 1
            ) as next_user
        ", array_merge([$year_start, $year_end], $achievement_refs, [$offset]));
        
        $remaining_users = (int)$wpdb->get_var($query);
        
        $this->debug_log('AJAX: Checking for more users', [
            'offset' => $offset,
            'remaining_users' => $remaining_users
        ]);
        
        return $remaining_users > 0;
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
                return $table_name;
            }
        }
        
        // If not found, search all tables
        $tables = $wpdb->get_col("SHOW TABLES");
        foreach ($tables as $table) {
            if (stripos($table, 'mycred') !== false && stripos($table, 'log') !== false) {
                return $table;
            }
        }
        
        return null;
    }
}