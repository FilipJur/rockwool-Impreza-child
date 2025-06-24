<?php

declare(strict_types=1);

namespace MistrFachman\Base;

/**
 * Base Points Handler - Abstract Foundation
 *
 * Provides common patterns for myCred points integration with "No Debt" policy.
 * Uses DUAL-PATHWAY architecture to handle WordPress execution context differences:
 * 
 * - Post Editor Flow: acf/save_post hook (guaranteed after ACF saves)
 * - AJAX Admin Flow: transition_post_status hook (works with pre-saved values)
 * 
 * DEFINITIVE SOLUTION: Eliminates race conditions by using appropriate hooks
 * for each execution context while maintaining unified transaction logic.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class PointsHandlerBase {

    /**
     * Get the post type slug for this domain
     */
    abstract protected function getPostType(): string;

    /**
     * Get the display name for this domain
     */
    abstract protected function getDomainDisplayName(): string;

    /**
     * Get the default points for this domain
     */
    abstract protected function getDefaultPoints(int $post_id = 0): int;

    /**
     * Get the ACF field selector that stores points (including group field prefix if applicable)
     */
    abstract protected function getPointsFieldSelector(): string;

    /**
     * Get the myCred reference type for this domain
     */
    abstract protected function getMyCredReference(): string;

    /**
     * Initialize dual-pathway hooks for different execution contexts
     */
    public function init_hooks(): void {
        // Path A: Handles the Post Editor workflow. Runs after ACF saves.
        add_action('acf/save_post', [$this, 'handle_editor_save'], 20);

        // Path B: Handles point REVOCATION for all workflows (AJAX and Editor).
        add_action('transition_post_status', [$this, 'handle_points_revocation'], 15, 3);
        
        // Deletion handling
        add_action('before_delete_post', [$this, 'handle_permanent_deletion'], 5);
    }

    /**
     * The callback for the Post Editor workflow.
     * Uses acf/save_post to ensure ACF data is completely saved.
     */
    public function handle_editor_save($post_id): void {
        $post_id = (int)$post_id;

        // Prevent this from running during an AJAX request, which has its own logic.
        if (wp_doing_ajax()) {
            return;
        }

        // Verify this is the correct post type and status
        if (get_post_type($post_id) !== $this->getPostType()) {
            return;
        }

        if (get_post_status($post_id) !== 'publish') {
            return;
        }

        $user_id = (int)get_post_field('post_author', $post_id);
        if (!$user_id || !get_userdata($user_id)) {
            return;
        }

        // By this point, get_field() is guaranteed to return the correct, final value from the editor.
        $points_to_award = $this->get_points_to_award($post_id);
        
        // If the field was empty, populate with default.
        if ($points_to_award <= 0) {
            $points_to_award = $this->getDefaultPoints($post_id);
            $this->set_points($post_id, $points_to_award);
            
            error_log("[{$this->getPostType()}:EDITOR] Auto-populated default points: {$points_to_award} for post {$post_id}");
        }

        error_log("[{$this->getPostType()}:EDITOR] Processing post editor save for post {$post_id} with {$points_to_award} points");
        
        $this->award_points($post_id, $user_id, $points_to_award);
    }

    /**
     * The PUBLIC service for awarding points - used by both pathways.
     * Handles point differences and audit trail updates.
     */
    public function award_points(int $post_id, int $user_id, int $points_to_award): bool {
        if (!function_exists('mycred_add')) {
            error_log("[{$this->getPostType()}:ERROR] myCred not available for points processing");
            return false;
        }

        if (!$this->validate_points_value($points_to_award)) {
            error_log("[{$this->getPostType()}:ERROR] Invalid points value '{$points_to_award}' for post {$post_id}");
            return false;
        }

        $points_already_awarded = (int)get_post_meta($post_id, "_{$this->getPostType()}_points_awarded", true);
        $point_difference = $points_to_award - $points_already_awarded;

        // Only process if there's a change
        if ($point_difference !== 0) {
            $log_message = $this->get_log_message($post_id, 'award', $point_difference);
            
            if (mycred_add($this->getMyCredReference(), $user_id, $point_difference, $log_message, $post_id)) {
                update_post_meta($post_id, "_{$this->getPostType()}_points_awarded", $points_to_award);
                
                error_log(sprintf(
                    '[%s:INFO] Points awarded for post %d: %+d points (total: %d)',
                    strtoupper($this->getPostType()),
                    $post_id,
                    $point_difference,
                    $points_to_award
                ));
                return true;
            } else {
                error_log(sprintf(
                    '[%s:ERROR] Failed to award points for post %d: %+d points',
                    strtoupper($this->getPostType()),
                    $post_id,
                    $point_difference
                ));
                return false;
            }
        }
        
        return true; // No change needed
    }

    /**
     * Handles REVOKING points when posts leave published status.
     * Works for both AJAX and Editor workflows.
     */
    public function handle_points_revocation(string $new_status, string $old_status, \WP_Post $post): void {
        if ($post->post_type !== $this->getPostType()) {
            return;
        }
        
        // Only handle revocation when leaving 'publish' status
        if ($old_status === 'publish' && $new_status !== 'publish') {
            $user_id = (int)$post->post_author;
            if ($user_id && get_userdata($user_id)) {
                $this->revoke_points($post->ID, $user_id, $new_status);
            }
        }
    }

    /**
     * Revoke points with "No Debt" policy
     */
    protected function revoke_points(int $post_id, int $user_id, string $new_status): void {
        if (!function_exists('mycred_add') || !function_exists('mycred_get_users_balance')) {
            return;
        }

        $points_awarded = (int)get_post_meta($post_id, "_{$this->getPostType()}_points_awarded", true);
        
        if ($points_awarded > 0) {
            // Get user's current balance to implement "No Debt" policy
            $current_balance = (int)mycred_get_users_balance($user_id);
            
            // Calculate maximum points we can revoke without creating debt
            $points_to_revoke = min($points_awarded, $current_balance);
            
            if ($points_to_revoke > 0) {
                // Get post title for better user experience
                $post_title = get_the_title($post_id) ?: "{$this->getDomainDisplayName()} #{$post_id}";
                
                // Revoke only the amount that won't create negative balance
                $result = mycred_add(
                    'revoke_' . $this->getPostType() . '_points',
                    $user_id,
                    -$points_to_revoke,
                    sprintf('Odebrání bodů: %s změněna na stav "%s"', $post_title, $this->get_status_label($new_status)),
                    $post_id
                );
                
                if ($result) {
                    // Update tracking to reflect actual points revoked
                    update_post_meta($post_id, "_{$this->getPostType()}_points_awarded", $points_awarded - $points_to_revoke);
                    
                    if ($points_to_revoke < $points_awarded) {
                        error_log("[{$this->getPostType()}:INFO] No Debt Policy: Revoked {$points_to_revoke}/{$points_awarded} points from post {$post_id} (balance: {$current_balance}, status: {$new_status})");
                    } else {
                        error_log("[{$this->getPostType()}:INFO] Revoked {$points_to_revoke} points from post {$post_id} (status: {$new_status})");
                    }
                }
            } else {
                // User has insufficient balance - log but don't revoke
                error_log("[{$this->getPostType()}:INFO] No Debt Policy: Cannot revoke {$points_awarded} points from post {$post_id} - user balance too low ({$current_balance})");
            }
        }
    }

    /**
     * Handle permanent deletion of posts
     */
    public function handle_permanent_deletion(int $post_id): void {
        if (get_post_type($post_id) === $this->getPostType()) {
            $user_id = (int)get_post_field('post_author', $post_id);
            if ($user_id > 0) {
                $this->revoke_points($post_id, $user_id, 'deleted');
            }
        }
    }

    /**
     * Get points to award with ACF fallback
     * This is now called from appropriate hooks for each context
     */
    protected function get_points_to_award(int $post_id): int {
        // Use field selector for reliable ACF access
        if (function_exists('get_field')) {
            $acf_points = get_field($this->getPointsFieldSelector(), $post_id);
            if (is_numeric($acf_points)) {
                return (int)$acf_points;
            }
        }
        
        // Fallback to post meta
        $meta_points = get_post_meta($post_id, $this->getPointsFieldSelector(), true);
        return is_numeric($meta_points) ? (int)$meta_points : 0;
    }

    /**
     * Set points value for a post
     */
    protected function set_points(int $post_id, int $points): bool {
        if (function_exists('update_field')) {
            $result = update_field($this->getPointsFieldSelector(), $points, $post_id);
            return $result !== false;
        } else {
            return update_post_meta($post_id, $this->getPointsFieldSelector(), $points) !== false;
        }
    }

    /**
     * Validate points value
     */
    protected function validate_points_value(int $points): bool {
        return $points >= 0 && $points <= 100000; // Reasonable upper limit
    }

    /**
     * Get appropriate log message with post title
     */
    protected function get_log_message(int $post_id, string $context, int $point_difference): string {
        $post_title = get_the_title($post_id) ?: "{$this->getDomainDisplayName()} #{$post_id}";
        
        return match ($context) {
            'award' => $point_difference > 0 
                ? sprintf('Udělení bodů za schválenou %s: %s', strtolower($this->getDomainDisplayName()), $post_title)
                : sprintf('Úprava bodů za %s: %s', strtolower($this->getDomainDisplayName()), $post_title),
            'save' => sprintf('Úprava bodů za %s: %s', strtolower($this->getDomainDisplayName()), $post_title),
            default => sprintf('Změna bodů za %s: %s', strtolower($this->getDomainDisplayName()), $post_title)
        };
    }

    /**
     * Get localized status label
     */
    protected function get_status_label(string $status): string {
        return match ($status) {
            'rejected' => 'odmítnuto',
            'pending' => 'čeká na schválení',
            'draft' => 'koncept',
            'trash' => 'koš',
            'deleted' => 'smazáno',
            default => $status
        };
    }
}