<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * DebugValidator - Diagnostic and Validation Utilities
 *
 * Provides debugging tools and validation utilities for the realizace system.
 * Helps diagnose configuration issues, status problems, and system health
 * for administrators and developers.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DebugValidator {

    public function __construct(
        private StatusManager $status_manager
    ) {}

    /**
     * Validate and fix rejected status issues
     * 
     * This method can be called to diagnose and fix rejected status problems
     * 
     * @param int|null $post_id Optional post ID to check specific post
     * @return array Diagnostic information
     */
    public function validate_rejected_status(?int $post_id = null): array {
        error_log('[REALIZACE:VALIDATE] Starting rejected status validation');
        
        $diagnostics = [
            'status_registered' => false,
            'post_statuses' => [],
            'specific_post' => null,
            'issues' => [],
            'fixes_applied' => [],
            'timestamp' => current_time('mysql')
        ];
        
        // Check if rejected status is properly registered
        $registered_statuses = get_post_stati();
        $diagnostics['post_statuses'] = array_keys($registered_statuses);
        
        if (isset($registered_statuses['rejected'])) {
            $diagnostics['status_registered'] = true;
            error_log('[REALIZACE:VALIDATE] Rejected status is registered');
            
            // Get detailed status info
            $diagnostics['rejected_status_details'] = [
                'label' => $registered_statuses['rejected']->label,
                'public' => $registered_statuses['rejected']->public,
                'show_in_admin_all_list' => $registered_statuses['rejected']->show_in_admin_all_list,
                'show_in_admin_status_list' => $registered_statuses['rejected']->show_in_admin_status_list,
                'protected' => $registered_statuses['rejected']->protected ?? false,
                'private' => $registered_statuses['rejected']->private ?? false,
            ];
        } else {
            $diagnostics['issues'][] = 'Rejected status not registered';
            error_log('[REALIZACE:VALIDATE] ERROR: Rejected status not registered');
            
            // Attempt to fix
            if ($this->status_manager->emergency_reregister()) {
                $diagnostics['fixes_applied'][] = 'Emergency re-registration successful';
            } else {
                $diagnostics['issues'][] = 'Emergency re-registration failed';
            }
        }
        
        // Check specific post if provided
        if ($post_id) {
            $diagnostics['specific_post'] = $this->validate_specific_post($post_id);
        }
        
        // Run additional system checks
        $diagnostics['system_checks'] = $this->run_system_checks();
        
        error_log('[REALIZACE:VALIDATE] Diagnostics: ' . json_encode($diagnostics));
        return $diagnostics;
    }

    /**
     * Validate a specific post
     *
     * @param int $post_id Post ID to validate
     * @return array Post validation results
     */
    private function validate_specific_post(int $post_id): array {
        $post = get_post($post_id);
        $validation = [
            'exists' => false,
            'id' => $post_id,
            'status' => null,
            'type' => null,
            'issues' => []
        ];
        
        if ($post) {
            $validation['exists'] = true;
            $validation['status'] = $post->post_status;
            $validation['type'] = $post->post_type;
            
            if ($post->post_type !== 'realizace') {
                $validation['issues'][] = "Post is not a realizace (type: {$post->post_type})";
            }
            
            if ($post->post_status === 'rejected') {
                error_log("[REALIZACE:VALIDATE] Post {$post_id} has rejected status correctly");
                
                // Check for rejection reason
                $rejection_reason = get_post_meta($post_id, 'duvod_zamitnuti', true);
                $validation['rejection_reason'] = $rejection_reason ?: null;
                
                if (empty($rejection_reason)) {
                    $validation['issues'][] = 'No rejection reason found';
                }
            } else {
                error_log("[REALIZACE:VALIDATE] Post {$post_id} status is {$post->post_status}, not rejected");
            }
        } else {
            $validation['issues'][] = "Post {$post_id} does not exist";
            error_log("[REALIZACE:VALIDATE] ERROR: Post {$post_id} does not exist");
        }
        
        return $validation;
    }

    /**
     * Run comprehensive system checks
     *
     * @return array System check results
     */
    private function run_system_checks(): array {
        $checks = [
            'wordpress_loaded' => did_action('wp_loaded') > 0,
            'init_complete' => did_action('init') > 0,
            'plugins_loaded' => did_action('plugins_loaded') > 0,
            'admin_context' => is_admin(),
            'current_hook' => current_action() ?: 'none',
            'realizace_post_type_exists' => post_type_exists('realizace'),
            'available_statuses_count' => count(get_post_stati()),
            'custom_statuses' => []
        ];
        
        // Check for custom statuses
        $all_statuses = get_post_stati();
        foreach ($all_statuses as $status_name => $status_obj) {
            if (!in_array($status_name, ['publish', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit'])) {
                $checks['custom_statuses'][] = $status_name;
            }
        }
        
        // Check realizace-specific configuration
        if (post_type_exists('realizace')) {
            $realizace_object = get_post_type_object('realizace');
            $checks['realizace_post_type_config'] = [
                'public' => $realizace_object->public,
                'show_ui' => $realizace_object->show_ui,
                'show_in_menu' => $realizace_object->show_in_menu,
                'supports' => $realizace_object->supports ?? []
            ];
        }
        
        return $checks;
    }

    /**
     * Get detailed diagnostic report
     *
     * @return array Comprehensive diagnostic report
     */
    public function get_diagnostic_report(): array {
        return [
            'system_info' => $this->get_system_info(),
            'status_validation' => $this->validate_rejected_status(),
            'realizace_stats' => $this->get_realizace_statistics(),
            'recent_errors' => $this->get_recent_errors()
        ];
    }

    /**
     * Get system information
     *
     * @return array System information
     */
    private function get_system_info(): array {
        return [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'theme' => get_template(),
            'child_theme' => get_stylesheet(),
            'active_plugins' => get_option('active_plugins'),
            'multisite' => is_multisite(),
            'debug_mode' => WP_DEBUG,
            'memory_limit' => ini_get('memory_limit'),
            'current_user_id' => get_current_user_id(),
            'current_user_roles' => wp_get_current_user()->roles ?? []
        ];
    }

    /**
     * Get realizace system statistics
     *
     * @return array Statistics about realizace posts
     */
    private function get_realizace_statistics(): array {
        $stats = [
            'total_posts' => 0,
            'by_status' => [],
            'total_points_awarded' => 0,
            'unique_authors' => 0
        ];
        
        // Get all realizace posts
        $posts = get_posts([
            'post_type' => 'realizace',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'all'
        ]);
        
        $stats['total_posts'] = count($posts);
        $authors = [];
        
        foreach ($posts as $post) {
            // Count by status
            if (!isset($stats['by_status'][$post->post_status])) {
                $stats['by_status'][$post->post_status] = 0;
            }
            $stats['by_status'][$post->post_status]++;
            
            // Track unique authors
            $authors[$post->post_author] = true;
            
            // Sum points for published posts
            if ($post->post_status === 'publish') {
                $points = get_post_meta($post->ID, '_realizace_points_awarded', true);
                if ($points) {
                    $stats['total_points_awarded'] += (int)$points;
                }
            }
        }
        
        $stats['unique_authors'] = count($authors);
        
        return $stats;
    }

    /**
     * Get recent error log entries (if accessible)
     *
     * @return array Array of recent error entries
     */
    private function get_recent_errors(): array {
        // This is a simplified implementation
        // In a real scenario, you might want to parse actual log files
        return [
            'note' => 'Error log parsing not implemented - check server error logs for [REALIZACE:*] entries',
            'logged_actions' => [
                'status_registration',
                'ajax_operations', 
                'save_operations',
                'validation_checks'
            ]
        ];
    }

    /**
     * Test AJAX endpoints
     *
     * @return array AJAX endpoint test results
     */
    public function test_ajax_endpoints(): array {
        $results = [
            'endpoints' => [
                'mistr_fachman_realizace_quick_action' => $this->test_ajax_action_exists('mistr_fachman_realizace_quick_action'),
                'mistr_fachman_bulk_approve_realizace' => $this->test_ajax_action_exists('mistr_fachman_bulk_approve_realizace')
            ],
            'nonce_actions' => [
                'mistr_fachman_realizace_action' => 'Quick action nonce',
                'mistr_fachman_bulk_approve' => 'Bulk approve nonce'
            ]
        ];
        
        return $results;
    }

    /**
     * Check if AJAX action is registered
     *
     * @param string $action AJAX action name
     * @return bool True if action is registered
     */
    private function test_ajax_action_exists(string $action): bool {
        global $wp_filter;
        
        $hook_name = "wp_ajax_{$action}";
        return isset($wp_filter[$hook_name]) && !empty($wp_filter[$hook_name]->callbacks);
    }

    /**
     * Generate debug report as formatted string
     *
     * @return string Formatted debug report
     */
    public function generate_debug_report_string(): string {
        $report = $this->get_diagnostic_report();
        
        $output = "=== REALIZACE DEBUG REPORT ===\n";
        $output .= "Generated: " . current_time('Y-m-d H:i:s') . "\n\n";
        
        foreach ($report as $section => $data) {
            $output .= strtoupper(str_replace('_', ' ', $section)) . ":\n";
            $output .= $this->format_array_for_output($data, 1) . "\n";
        }
        
        return $output;
    }

    /**
     * Format array data for text output
     *
     * @param array $data Data to format
     * @param int $indent Indentation level
     * @return string Formatted output
     */
    private function format_array_for_output(array $data, int $indent = 0): string {
        $output = '';
        $prefix = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $output .= "{$prefix}{$key}:\n";
                $output .= $this->format_array_for_output($value, $indent + 1);
            } else {
                $value_str = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
                $output .= "{$prefix}{$key}: {$value_str}\n";
            }
        }
        
        return $output;
    }
}