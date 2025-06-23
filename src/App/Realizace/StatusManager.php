<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Status Manager - Custom Post Status Operations
 *
 * Handles registration, verification, and management of custom post statuses
 * for the realizace post type. Provides comprehensive debugging and validation.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class StatusManager {

    /**
     * Initialize status management hooks
     */
    public function init_hooks(): void {
        // Multiple registration attempts to ensure status is available
        add_action('plugins_loaded', [$this, 'register_rejected_status'], 1);
        add_action('init', [$this, 'register_rejected_status'], 1);
        add_action('wp_loaded', [$this, 'verify_rejected_status'], 1);
        add_filter('display_post_states', [$this, 'add_post_state_labels'], 10, 2);
    }

    /**
     * Register the custom 'rejected' post status with smart timing detection
     */
    public function register_rejected_status(): void {
        static $registered = false;
        
        // Prevent multiple registrations
        if ($registered) {
            error_log('[REALIZACE:STATUS] Rejected status already registered, skipping...');
            return;
        }
        
        error_log('[REALIZACE:STATUS] Registering rejected status...');
        error_log('[REALIZACE:STATUS] Current hook: ' . (current_action() ?: 'none'));
        error_log('[REALIZACE:STATUS] WordPress loaded: ' . (did_action('wp_loaded') ? 'yes' : 'no'));
        
        // Check if status already exists (from another source)
        $existing_statuses = get_post_stati();
        if (isset($existing_statuses['rejected'])) {
            error_log('[REALIZACE:STATUS] Rejected status already exists, marking as registered');
            $registered = true;
            return;
        }
        
        $status_args = [
            'label' => 'Odmítnuto',
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'protected' => true,  // Ensures it's not treated as public
            'private' => true,    // Marks it as private
            'publicly_queryable' => false,
            '_builtin' => false,  // Mark as custom status
            'label_count' => _n_noop(
                'Odmítnuto <span class="count">(%s)</span>',
                'Odmítnuto <span class="count">(%s)</span>'
            ),
        ];
        
        error_log('[REALIZACE:STATUS] Rejected status args: ' . json_encode($status_args));
        
        $result = register_post_status('rejected', $status_args);
        
        error_log('[REALIZACE:STATUS] register_post_status returned: ' . json_encode($result));
        
        // Verify registration immediately
        $registered_statuses = get_post_stati();
        if (isset($registered_statuses['rejected'])) {
            error_log('[REALIZACE:STATUS] SUCCESS: Rejected status confirmed in registered statuses');
            $registered = true;
        } else {
            error_log('[REALIZACE:STATUS] ERROR: Rejected status NOT found after registration');
            error_log('[REALIZACE:STATUS] Available statuses: ' . implode(', ', array_keys($registered_statuses)));
        }
    }

    /**
     * Verify rejected status registration after WordPress is fully loaded
     */
    public function verify_rejected_status(): void {
        error_log('[REALIZACE:STATUS] Verifying rejected status after wp_loaded...');
        
        $registered_statuses = get_post_stati();
        $available_keys = array_keys($registered_statuses);
        
        error_log('[REALIZACE:STATUS] All registered statuses: ' . implode(', ', $available_keys));
        
        if (isset($registered_statuses['rejected'])) {
            error_log('[REALIZACE:STATUS] SUCCESS: Rejected status found in registration');
            $rejected_status = $registered_statuses['rejected'];
            
            // Check if it's an object or string
            if (is_object($rejected_status)) {
                error_log('[REALIZACE:STATUS] Properties: ' . json_encode([
                    'label' => property_exists($rejected_status, 'label') ? $rejected_status->label : 'not set',
                    'public' => property_exists($rejected_status, 'public') ? $rejected_status->public : 'not set',
                    'show_in_admin_all_list' => property_exists($rejected_status, 'show_in_admin_all_list') ? $rejected_status->show_in_admin_all_list : 'not set',
                    'show_in_admin_status_list' => property_exists($rejected_status, 'show_in_admin_status_list') ? $rejected_status->show_in_admin_status_list : 'not set'
                ]));
            } else {
                error_log('[REALIZACE:STATUS] Status is not an object, type: ' . gettype($rejected_status) . ', value: ' . json_encode($rejected_status));
            }
        } else {
            error_log('[REALIZACE:STATUS] CRITICAL: Rejected status NOT found after wp_loaded!');
            error_log('[REALIZACE:STATUS] This will cause AJAX reject actions to fail');
        }
    }

    /**
     * Add custom post state labels in admin list view
     *
     * @param array $post_states Current post states
     * @param \WP_Post $post Post object
     * @return array Modified post states
     */
    public function add_post_state_labels(array $post_states, \WP_Post $post): array {
        if ($post->post_type !== 'realizace') {
            return $post_states;
        }

        if ($post->post_status === 'rejected') {
            $post_states['rejected'] = 'Odmítnuto';
        }

        return $post_states;
    }

    /**
     * Check if rejected status is available for post updates
     *
     * @return bool True if rejected status is available
     */
    public function is_rejected_status_available(): bool {
        $available_statuses = get_post_stati(['show_in_admin_status_list' => true]);
        return isset($available_statuses['rejected']);
    }

    /**
     * Emergency re-registration of rejected status
     * Used as fallback when status is missing during AJAX operations
     *
     * @return bool True if re-registration succeeded
     */
    public function emergency_register_rejected_status(): bool {
        error_log('[REALIZACE:STATUS] EMERGENCY: Attempting re-registration...');
        
        $this->register_rejected_status();
        
        // Check if it worked
        $available_statuses = get_post_stati(['show_in_admin_status_list' => true]);
        $success = isset($available_statuses['rejected']);
        
        if ($success) {
            error_log('[REALIZACE:STATUS] EMERGENCY: Re-registration succeeded!');
        } else {
            error_log('[REALIZACE:STATUS] EMERGENCY: Re-registration FAILED!');
        }
        
        return $success;
    }

    /**
     * Validate rejected status implementation
     * 
     * @param int|null $post_id Optional post ID to check specific post
     * @return array Diagnostic information
     */
    public function validate_status_implementation(?int $post_id = null): array {
        error_log('[REALIZACE:STATUS] Starting status validation');
        
        $diagnostics = [
            'status_registered' => false,
            'available_statuses' => [],
            'specific_post' => null,
            'issues' => [],
        ];
        
        // Check if rejected status is properly registered
        $registered_statuses = get_post_stati();
        $diagnostics['available_statuses'] = array_keys($registered_statuses);
        
        if (isset($registered_statuses['rejected'])) {
            $diagnostics['status_registered'] = true;
            error_log('[REALIZACE:STATUS] Validation: Rejected status is registered');
        } else {
            $diagnostics['issues'][] = 'Rejected status not registered';
            error_log('[REALIZACE:STATUS] Validation: ERROR - Rejected status not registered');
        }
        
        // Check specific post if provided
        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                $diagnostics['specific_post'] = [
                    'id' => $post->ID,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'exists' => true
                ];
                
                if ($post->post_status === 'rejected') {
                    error_log("[REALIZACE:STATUS] Validation: Post {$post_id} has rejected status correctly");
                } else {
                    error_log("[REALIZACE:STATUS] Validation: Post {$post_id} status is {$post->post_status}");
                }
            } else {
                $diagnostics['specific_post'] = ['exists' => false];
                $diagnostics['issues'][] = "Post {$post_id} does not exist";
                error_log("[REALIZACE:STATUS] Validation: ERROR - Post {$post_id} does not exist");
            }
        }
        
        error_log('[REALIZACE:STATUS] Validation complete: ' . json_encode($diagnostics));
        return $diagnostics;
    }
}