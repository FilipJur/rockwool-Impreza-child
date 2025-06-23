<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Points Handler - myCred Integration
 *
 * Core business logic that connects ACF fields to myCred points.
 * Handles point awards and corrections based on realizace approval status.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PointsHandler {

    /**
     * Initialize points handling hooks
     */
    public function init_hooks(): void {
        add_action('save_post_realizace', [$this, 'handle_points_on_save'], 20, 2);
    }

    /**
     * Handle point awards and corrections when a realizace is saved
     * 
     * Points are only awarded for published (approved) realizace posts.
     * Supports both adding and subtracting points based on changes.
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     */
    public function handle_points_on_save(int $post_id, \WP_Post $post): void {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Skip if user doesn't have permission to edit this post
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Only award points for published (approved) posts
        if ($post->post_status !== 'publish') {
            return;
        }

        // Validate post author exists
        $user_id = (int)$post->post_author;
        if (!get_userdata($user_id)) {
            error_log("[REALIZACE:ERROR] Invalid user ID {$user_id} for post {$post_id}");
            return;
        }

        $this->process_point_change($post_id, $user_id, 'save');
    }

    /**
     * Handle points when post status changes
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param \WP_Post $post Post object
     */
    public function handle_status_change_points(string $new_status, string $old_status, \WP_Post $post): void {
        if ($post->post_type !== 'realizace') {
            return;
        }

        $user_id = (int)$post->post_author;
        if (!get_userdata($user_id)) {
            return;
        }

        // Handle status transitions
        if ($old_status !== 'publish' && $new_status === 'publish') {
            // Post was approved - award points
            $this->process_point_change($post->ID, $user_id, 'approve');
        } elseif ($old_status === 'publish' && $new_status !== 'publish') {
            // Post was un-approved - revoke points
            $this->revoke_points($post->ID, $user_id, $new_status);
        }
    }

    /**
     * Process point changes with proper validation and error handling
     *
     * @param int $post_id Post ID
     * @param int $user_id User ID
     * @param string $context Action context (save|approve)
     */
    private function process_point_change(int $post_id, int $user_id, string $context): void {
        // Check if myCred is available
        if (!function_exists('mycred_add')) {
            error_log('[REALIZACE:ERROR] myCred not available for points processing');
            return;
        }

        // Get points to award from ACF field with fallback
        $points_to_award = $this->get_points_to_award($post_id);
        
        // Validate points value
        if (!$this->validate_points_value($points_to_award)) {
            error_log("[REALIZACE:ERROR] Invalid points value '{$points_to_award}' for post {$post_id}");
            return;
        }
        
        // Get points already awarded (audit trail)
        $points_already_awarded = (int)get_post_meta($post_id, '_realizace_points_awarded', true);
        
        // Calculate the difference
        $point_difference = $points_to_award - $points_already_awarded;
        
        // Only process if there's a change
        if ($point_difference !== 0) {
            $log_message = $this->get_log_message($context, $point_difference);
            
            // Award or deduct points via myCred
            $result = mycred_add(
                'approval_of_realizace',
                $user_id,
                $point_difference,
                $log_message,
                $post_id
            );
            
            if ($result) {
                // Update audit trail
                update_post_meta($post_id, '_realizace_points_awarded', $points_to_award);
                
                error_log(sprintf(
                    '[REALIZACE:INFO] Points processed for post %d: %+d points (total: %d) - %s',
                    $post_id,
                    $point_difference,
                    $points_to_award,
                    $context
                ));
            } else {
                error_log(sprintf(
                    '[REALIZACE:ERROR] Failed to process points for post %d: %+d points - %s',
                    $post_id,
                    $point_difference,
                    $context
                ));
            }
        }
    }

    /**
     * Revoke all points when post is rejected or unpublished
     *
     * @param int $post_id Post ID
     * @param int $user_id User ID
     * @param string $new_status New status
     */
    private function revoke_points(int $post_id, int $user_id, string $new_status): void {
        if (!function_exists('mycred_add')) {
            return;
        }

        $points_awarded = (int)get_post_meta($post_id, '_realizace_points_awarded', true);
        
        if ($points_awarded > 0) {
            $result = mycred_add(
                'revoke_realizace_points',
                $user_id,
                -$points_awarded,
                'Odebrání bodů - realizace změněna na: %s (ID: %d)',
                $this->get_status_label($new_status),
                $post_id
            );
            
            if ($result) {
                update_post_meta($post_id, '_realizace_points_awarded', 0);
                error_log("[REALIZACE:INFO] Revoked {$points_awarded} points from post {$post_id} (status: {$new_status})");
            } else {
                error_log("[REALIZACE:ERROR] Failed to revoke {$points_awarded} points from post {$post_id}");
            }
        }
    }

    /**
     * Get points to award with ACF fallback
     *
     * @param int $post_id Post ID
     * @return int Points to award
     */
    private function get_points_to_award(int $post_id): int {
        // Try ACF first
        if (function_exists('get_field')) {
            $acf_points = get_field('pridelene_body', $post_id);
            if (is_numeric($acf_points)) {
                return (int)$acf_points;
            }
        }
        
        // Fallback to post meta
        $meta_points = get_post_meta($post_id, 'pridelene_body', true);
        return is_numeric($meta_points) ? (int)$meta_points : 0;
    }

    /**
     * Validate points value
     *
     * @param int $points Points value
     * @return bool True if valid
     */
    private function validate_points_value(int $points): bool {
        return $points >= 0 && $points <= 10000; // Reasonable upper limit
    }

    /**
     * Get appropriate log message based on context
     *
     * @param string $context Action context
     * @param int $point_difference Point difference
     * @return string Log message
     */
    private function get_log_message(string $context, int $point_difference): string {
        return match ($context) {
            'approve' => $point_difference > 0 
                ? 'Udělení bodů za schválenou realizaci ID: %d'
                : 'Úprava bodů za realizaci ID: %d',
            'save' => 'Úprava bodů za realizaci ID: %d',
            default => 'Změna bodů za realizaci ID: %d'
        };
    }

    /**
     * Get localized status label
     *
     * @param string $status Post status
     * @return string Localized label
     */
    private function get_status_label(string $status): string {
        return match ($status) {
            'rejected' => 'odmítnuto',
            'pending' => 'čeká na schválení',
            'draft' => 'koncept',
            'trash' => 'koš',
            default => $status
        };
    }
}