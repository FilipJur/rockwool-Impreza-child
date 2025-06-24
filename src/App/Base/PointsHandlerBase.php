<?php

declare(strict_types=1);

namespace MistrFachman\Base;

/**
 * Base Points Handler - Abstract Foundation
 *
 * Provides common patterns for myCred points integration with "No Debt" policy.
 * Handles point awards, corrections, and revocations across domains.
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
     * Initialize points handling hooks
     */
    public function init_hooks(): void {
        add_action('transition_post_status', [$this, 'handle_status_change_points'], 15, 3);
        add_action('before_delete_post', [$this, 'handle_permanent_deletion'], 5);
    }


    /**
     * Handle points when post status changes
     */
    public function handle_status_change_points(string $new_status, string $old_status, \WP_Post $post): void {
        if ($post->post_type !== $this->getPostType()) {
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
     * Process point changes with automatic default population
     */
    protected function process_point_change(int $post_id, int $user_id, string $context): void {
        // Check if myCred is available
        if (!function_exists('mycred_add')) {
            error_log("[{$this->getPostType()}:ERROR] myCred not available for points processing");
            return;
        }

        // Get points to award - ensure default is set if empty
        $points_to_award = $this->get_points_to_award($post_id);
        
        // If no points set, populate with default
        if ($points_to_award === 0) {
            $default_points = $this->getDefaultPoints($post_id);
            if ($default_points > 0) {
                $this->set_points($post_id, $default_points);
                $points_to_award = $default_points;
                
                error_log("[{$this->getPostType()}:INFO] Auto-populated default points: {$default_points} for post {$post_id}");
            }
        }
        
        // Validate points value
        if (!$this->validate_points_value($points_to_award)) {
            error_log("[{$this->getPostType()}:ERROR] Invalid points value '{$points_to_award}' for post {$post_id}");
            return;
        }
        
        // Get points already awarded (audit trail)
        $points_already_awarded = (int)get_post_meta($post_id, "_{$this->getPostType()}_points_awarded", true);
        
        // Calculate the difference
        $point_difference = $points_to_award - $points_already_awarded;
        
        // Only process if there's a change
        if ($point_difference !== 0) {
            $log_message = $this->get_log_message($post_id, $context, $point_difference);
            
            // Award or deduct points via myCred
            $result = mycred_add(
                $this->getMyCredReference(),
                $user_id,
                $point_difference,
                $log_message,
                $post_id
            );
            
            if ($result) {
                // Update audit trail
                update_post_meta($post_id, "_{$this->getPostType()}_points_awarded", $points_to_award);
                
                error_log(sprintf(
                    '[%s:INFO] Points processed for post %d: %+d points (total: %d) - %s',
                    strtoupper($this->getPostType()),
                    $post_id,
                    $point_difference,
                    $points_to_award,
                    $context
                ));
            } else {
                error_log(sprintf(
                    '[%s:ERROR] Failed to process points for post %d: %+d points - %s',
                    strtoupper($this->getPostType()),
                    $post_id,
                    $point_difference,
                    $context
                ));
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
     * Get points to award with ACF fallback and default population
     */
    protected function get_points_to_award(int $post_id): int {
        // SINGLE SOURCE OF TRUTH: Use abstract method for all domains
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
        // SINGLE SOURCE OF TRUTH: Use abstract method for all domains
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
            'approve' => $point_difference > 0 
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