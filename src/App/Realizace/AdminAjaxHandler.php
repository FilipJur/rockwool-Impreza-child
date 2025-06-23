<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Admin AJAX Handler - AJAX Request Processing
 *
 * Handles all AJAX endpoints for realizace management including quick actions
 * (approve/reject) and bulk operations. Provides comprehensive error handling
 * and status verification.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminAjaxHandler {

    public function __construct(
        private StatusManager $status_manager
    ) {}

    /**
     * Initialize AJAX hooks
     */
    public function init_hooks(): void {
        // AJAX handlers for quick actions
        add_action('wp_ajax_mistr_fachman_realizace_quick_action', [$this, 'handle_quick_action_ajax']);
        add_action('wp_ajax_mistr_fachman_bulk_approve_realizace', [$this, 'handle_bulk_approve_ajax']);
        add_action('wp_ajax_mistr_fachman_update_acf_field', [$this, 'handle_update_acf_field_ajax']);
    }

    /**
     * Handle AJAX quick actions for realizace (approve/reject)
     */
    public function handle_quick_action_ajax(): void {
        error_log('[REALIZACE:AJAX] Quick action called');
        error_log('[REALIZACE:AJAX] POST data: ' . json_encode($_POST));
        
        try {
            // Verify nonce and permissions
            check_ajax_referer('mistr_fachman_realizace_action', 'nonce');
            error_log('[REALIZACE:AJAX] Nonce verified');
            
            if (!current_user_can('edit_posts')) {
                error_log('[REALIZACE:AJAX] User lacks edit_posts capability');
                wp_send_json_error(['message' => 'Insufficient permissions']);
            }
            
            $post_id = (int)($_POST['post_id'] ?? 0);
            $action = sanitize_text_field($_POST['realizace_action'] ?? '');
            
            error_log("[REALIZACE:AJAX] Parameters: post_id={$post_id}, action={$action}");
            
            if (!$post_id || !in_array($action, ['approve', 'reject'])) {
                error_log('[REALIZACE:AJAX] Invalid parameters');
                wp_send_json_error(['message' => 'Invalid parameters']);
            }
            
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'realizace') {
                error_log('[REALIZACE:AJAX] Invalid post');
                wp_send_json_error(['message' => 'Invalid post']);
            }
            
            if ($action === 'approve') {
                $this->process_approve_action($post_id, $post);
            } else {
                // Get rejection reason from request
                $rejection_reason = sanitize_textarea_field($_POST['rejection_reason'] ?? '');
                $this->process_reject_action($post_id, $post, $rejection_reason);
            }
            
        } catch (\Exception $e) {
            error_log('[REALIZACE:AJAX] Exception in quick action: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Chyba při zpracování: ' . $e->getMessage()]);
        }
    }

    /**
     * Process approve action with points validation
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     */
    private function process_approve_action(int $post_id, \WP_Post $post): void {
        error_log("[REALIZACE:AJAX] Processing APPROVE action for post {$post_id}");
        
        // Get points to award from POST data
        $points_to_award = isset($_POST['points']) ? (int)$_POST['points'] : 0;
        
        // Validate points
        if ($points_to_award <= 0) {
            wp_send_json_error(['message' => 'Počet bodů musí být kladné číslo.']);
        }
        
        // Save points to ACF field before updating status
        if (function_exists('update_field')) {
            update_field('pridelene_body', $points_to_award, $post_id);
        } else {
            update_post_meta($post_id, 'pridelene_body', $points_to_award);
        }
        
        error_log("[REALIZACE:AJAX] Set points to {$points_to_award} for post {$post_id}");
        
        // Now update post status - this will trigger the points award via PointsHandler
        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'publish'
        ]);
        
        $this->handle_update_result($result, $post_id, 'approve');
    }

    /**
     * Process reject action with comprehensive status validation
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @param string $rejection_reason Rejection reason from user input
     */
    private function process_reject_action(int $post_id, \WP_Post $post, string $rejection_reason = ''): void {
        error_log("[REALIZACE:AJAX] Processing REJECT action for post {$post_id}");
        error_log("[REALIZACE:AJAX] Current post status before reject: {$post->post_status}");
        
        // Check if rejected status is available
        if (!$this->status_manager->is_rejected_status_available()) {
            error_log("[REALIZACE:AJAX] Rejected status not available - attempting emergency registration");
            
            if (!$this->status_manager->emergency_register_rejected_status()) {
                wp_send_json_error(['message' => 'Chyba: Status "odmítnuto" není dostupný. Kontaktujte administrátora.']);
            }
        }
        
        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'rejected'
        ]);
        
        error_log("[REALIZACE:AJAX] wp_update_post result for reject: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        if ($result) {
            // Verify the status was actually set
            $updated_post = get_post($post_id);
            error_log("[REALIZACE:AJAX] Post status after update: {$updated_post->post_status}");
            
            if ($updated_post->post_status !== 'rejected') {
                error_log("[REALIZACE:AJAX] ERROR: Status was not properly set to rejected! Got: {$updated_post->post_status}");
            }
        }
        
        // Set rejection reason from user input or use default
        if (!empty($rejection_reason)) {
            update_post_meta($post_id, 'duvod_zamitnuti', $rejection_reason);
            error_log("[REALIZACE:AJAX] Set user-provided rejection reason: " . $rejection_reason);
        } else {
            // Only set default if no existing reason and no user input
            $existing_reason = get_post_meta($post_id, 'duvod_zamitnuti', true);
            if (empty($existing_reason)) {
                update_post_meta($post_id, 'duvod_zamitnuti', 'Rychle odmítnuto administrátorem');
                error_log("[REALIZACE:AJAX] Added default rejection reason");
            }
        }
        
        $this->handle_update_result($result, $post_id, 'reject');
    }

    /**
     * Handle the result of wp_update_post and send appropriate response
     *
     * @param int|\WP_Error $result Result from wp_update_post
     * @param int $post_id Post ID
     * @param string $action Action performed
     */
    private function handle_update_result($result, int $post_id, string $action): void {
        if (is_wp_error($result)) {
            error_log('[REALIZACE:AJAX] wp_update_post failed: ' . $result->get_error_message());
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        // CRITICAL: Verify the post still exists and wasn't deleted
        $final_check_post = get_post($post_id);
        if (!$final_check_post) {
            error_log("[REALIZACE:AJAX] CRITICAL: Post {$post_id} was DELETED during status update!");
            wp_send_json_error(['message' => 'Chyba: Příspěvek byl neočekávaně smazán.']);
        } else {
            error_log("[REALIZACE:AJAX] Post {$post_id} still exists with status: {$final_check_post->post_status}");
        }
        
        error_log("[REALIZACE:AJAX] Post {$post_id} successfully updated to {$action}");
        wp_send_json_success([
            'message' => $action === 'approve' ? 'Realizace byla schválena' : 'Realizace byla odmítnuta',
            'new_status' => $action === 'approve' ? 'publish' : 'rejected'
        ]);
    }

    /**
     * Handle bulk approve AJAX action
     */
    public function handle_bulk_approve_ajax(): void {
        error_log('[REALIZACE:AJAX] Bulk approve called');
        
        // Verify nonce and permissions
        check_ajax_referer('mistr_fachman_bulk_approve', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $user_id = (int)($_POST['user_id'] ?? 0);
        $points_data_json = $_POST['points_data'] ?? '{}';
        
        if (!$user_id || !get_userdata($user_id)) {
            wp_send_json_error(['message' => 'Invalid user']);
        }
        
        // Parse points data
        $points_data = json_decode($points_data_json, true);
        if (!is_array($points_data)) {
            $points_data = [];
        }
        
        try {
            $approved_count = $this->process_bulk_approve($user_id, $points_data);
            
            wp_send_json_success([
                'message' => sprintf('Schváleno %d realizací', $approved_count),
                'approved_count' => $approved_count
            ]);
            
        } catch (\Exception $e) {
            error_log('[REALIZACE:AJAX] Bulk approve exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Chyba při hromadném schvalování: ' . $e->getMessage()]);
        }
    }

    /**
     * Process bulk approve operation for a user
     *
     * @param int $user_id User ID
     * @param array $points_data Array mapping post IDs to points values
     * @return int Number of approved posts
     */
    private function process_bulk_approve(int $user_id, array $points_data = []): int {
        // Get all pending realizace for user
        $pending_posts = get_posts([
            'post_type' => 'realizace',
            'author' => $user_id,
            'post_status' => 'pending',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        error_log("[REALIZACE:AJAX] Found " . count($pending_posts) . " pending posts for user {$user_id}");
        
        $approved_count = 0;
        foreach ($pending_posts as $post_id) {
            // Get points for this specific post
            $points = isset($points_data[$post_id]) ? (int)$points_data[$post_id] : 0;
            
            // Skip posts without points specified
            if ($points <= 0) {
                error_log("[REALIZACE:AJAX] Skipping post {$post_id} - no points specified");
                continue;
            }
            
            $result = wp_update_post([
                'ID' => $post_id,
                'post_status' => 'publish'
            ]);
            
            if (!is_wp_error($result)) {
                // Set points for the approved post
                update_field('pridelene_body', $points, $post_id);
                error_log("[REALIZACE:AJAX] Bulk approved post {$post_id} with {$points} points");
                
                // Award points through the points handler
                if (class_exists('\\MistrFachman\\Realizace\\PointsHandler')) {
                    $points_handler = new \MistrFachman\Realizace\PointsHandler();
                    $points_handler->process_realizace_points($post_id, 'approve');
                }
                
                $approved_count++;
            } else {
                error_log("[REALIZACE:AJAX] Failed to bulk approve post {$post_id}: " . $result->get_error_message());
            }
        }
        
        return $approved_count;
    }

    /**
     * Handle ACF field update AJAX action
     */
    public function handle_update_acf_field_ajax(): void {
        error_log('[REALIZACE:AJAX] Update ACF field called');
        error_log('[REALIZACE:AJAX] POST data: ' . json_encode($_POST));
        
        try {
            // Verify nonce and permissions
            check_ajax_referer('mistr_fachman_realizace_action', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
            }
            
            $post_id = (int)($_POST['post_id'] ?? 0);
            $field_name = sanitize_text_field($_POST['field_name'] ?? '');
            $field_value = sanitize_textarea_field($_POST['field_value'] ?? '');
            
            if (!$post_id || !$field_name) {
                wp_send_json_error(['message' => 'Invalid parameters']);
            }
            
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'realizace') {
                wp_send_json_error(['message' => 'Invalid post']);
            }
            
            // Update the ACF field
            $result = update_field($field_name, $field_value, $post_id);
            
            if ($result !== false) {
                error_log("[REALIZACE:AJAX] Updated ACF field '{$field_name}' for post {$post_id}");
                wp_send_json_success([
                    'message' => 'Field updated successfully',
                    'field_name' => $field_name,
                    'field_value' => $field_value
                ]);
            } else {
                error_log("[REALIZACE:AJAX] Failed to update ACF field '{$field_name}' for post {$post_id}");
                wp_send_json_error(['message' => 'Failed to update field']);
            }
            
        } catch (\Exception $e) {
            error_log('[REALIZACE:AJAX] Exception in update ACF field: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error updating field: ' . $e->getMessage()]);
        }
    }

    /**
     * Validate AJAX request parameters
     *
     * @param array $required_params Required parameter names
     * @return array Validated parameters
     * @throws \InvalidArgumentException If validation fails
     */
    private function validate_ajax_params(array $required_params): array {
        $validated = [];
        
        foreach ($required_params as $param) {
            if (!isset($_POST[$param])) {
                throw new \InvalidArgumentException("Missing required parameter: {$param}");
            }
            
            $validated[$param] = sanitize_text_field($_POST[$param]);
        }
        
        return $validated;
    }

    /**
     * Get sanitized integer from POST data
     *
     * @param string $key POST key
     * @param int $default Default value
     * @return int Sanitized integer
     */
    private function get_post_int(string $key, int $default = 0): int {
        return isset($_POST[$key]) ? (int)$_POST[$key] : $default;
    }

    /**
     * Get sanitized string from POST data
     *
     * @param string $key POST key
     * @param string $default Default value
     * @return string Sanitized string
     */
    private function get_post_string(string $key, string $default = ''): string {
        return isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : $default;
    }
}