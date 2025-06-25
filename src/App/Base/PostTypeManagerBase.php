<?php

declare(strict_types=1);

namespace MistrFachman\Base;

/**
 * Base Post Type Manager - Abstract Foundation
 *
 * Provides common patterns for managing custom post types with approval workflows.
 * Designed to be extended by domain-specific managers (Realizace, Faktury, etc.).
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class PostTypeManagerBase {

    /**
     * Get the post type slug for this domain
     */
    abstract protected function getPostType(): string;

    /**
     * Get the calculated points value for this domain
     * Can be static (like Realizace) or dynamic (like Faktury)
     * 
     * @param int $post_id Optional post ID for dynamic calculations
     * @return int Calculated points value
     */
    abstract protected function getCalculatedPoints(int $post_id = 0): int;

    /**
     * Get the ACF field selector that stores points for this domain (including group field prefix if applicable)
     */
    abstract protected function getPointsFieldSelector(): string;

    /**
     * Get the display name for this domain (e.g., "Realizace", "Faktury")
     */
    abstract protected function getDomainDisplayName(): string;

    /**
     * Initialize common hooks for post type management
     */
    public function init_common_hooks(): void {
        add_action('save_post_' . $this->getPostType(), [$this, 'handle_post_save'], 10, 2);
        add_action('transition_post_status', [$this, 'handle_status_transition'], 10, 3);
        add_action('wp_after_insert_post', [$this, 'populate_default_points'], 20, 2);
    }

    /**
     * Handle post save - populate default points if needed
     */
    public function handle_post_save(int $post_id, \WP_Post $post): void {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $this->ensure_default_points($post_id);
    }

    /**
     * Handle status transitions for logging
     */
    public function handle_status_transition(string $new_status, string $old_status, \WP_Post $post): void {
        if ($post->post_type !== $this->getPostType()) {
            return;
        }

        error_log(sprintf(
            '[%s:MANAGER] Status transition: %s → %s for post #%d',
            strtoupper($this->getPostType()),
            $old_status,
            $new_status,
            $post->ID
        ));
    }

    /**
     * Populate default points after post insertion
     */
    public function populate_default_points(int $post_id, \WP_Post $post, bool $update = false): void {
        if ($post->post_type !== $this->getPostType()) {
            return;
        }

        // Only set defaults for new posts or posts without points
        if (!$update || $this->get_current_points($post_id) === 0) {
            $this->ensure_default_points($post_id);
        }
    }

    /**
     * Ensure post has default points if none are set
     */
    protected function ensure_default_points(int $post_id): void {
        // SINGLE SOURCE OF TRUTH: Use abstract methods for all domains
        $current_points = $this->get_current_points($post_id);
        
        if ($current_points === 0) {
            $calculated_points = $this->getCalculatedPoints($post_id);
            
            if ($calculated_points > 0) {
                $this->set_points($post_id, $calculated_points);
                
                error_log(sprintf(
                    '[%s:MANAGER] Auto-populated calculated points: %d for post #%d',
                    strtoupper($this->getPostType()),
                    $calculated_points,
                    $post_id
                ));
            }
        }
    }

    /**
     * Get current points value for a post
     * SINGLE SOURCE OF TRUTH: Always use the same field selector
     */
    protected function get_current_points(int $post_id): int {
        if (function_exists('get_field')) {
            $points = get_field($this->getPointsFieldSelector(), $post_id);
        } else {
            $points = get_post_meta($post_id, $this->getPointsFieldSelector(), true);
        }

        return is_numeric($points) ? (int)$points : 0;
    }

    /**
     * Set points value for a post
     * SINGLE SOURCE OF TRUTH: Always use the same field selector
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
     * Get posts by status for this domain
     */
    protected function get_posts_by_status(int $user_id, string $status, int $limit = -1): array {
        return get_posts([
            'post_type' => $this->getPostType(),
            'author' => $user_id,
            'post_status' => $status,
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
    }

    /**
     * Get domain configuration for frontend localization
     */
    public function get_domain_config(): array {
        return [
            'post_type' => $this->getPostType(),
            'display_name' => $this->getDomainDisplayName(),
            'default_points' => $this->getCalculatedPoints(),
            'points_field' => $this->getPointsFieldSelector()
        ];
    }
}