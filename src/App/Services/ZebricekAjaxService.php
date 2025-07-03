<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Žebříček AJAX Service
 *
 * Handles AJAX requests for leaderboard pagination functionality.
 * Provides secure endpoints for loading more users using ZebricekDataService.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZebricekAjaxService
{
    private UserService $user_service;
    private ZebricekDataService $zebricek_service;

    public function __construct(UserService $user_service, ZebricekDataService $zebricek_service) {
        $this->user_service = $user_service;
        $this->zebricek_service = $zebricek_service;
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
            
            // Get leaderboard data via service - no caching for AJAX
            $current_year = date('Y');
            $leaderboard_data = $this->zebricek_service->get_leaderboard_data($limit, $offset, $current_year);
            
            // Render rows using templates
            $user_rows_html = $this->render_user_rows($leaderboard_data, $offset, $is_full_member);
            
            // Check if there are more users available
            $has_more = $this->check_has_more_users($offset + $limit, $current_year);

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
     * Uses a lightweight query to check without fetching actual data
     */
    private function check_has_more_users(int $offset, string $year): bool
    {
        // Get one more user at the offset to see if there are more
        $test_data = $this->zebricek_service->get_leaderboard_data(1, $offset, $year);
        return !empty($test_data);
    }
}