<?php

declare(strict_types=1);

namespace MistrFachman\Base;

use MistrFachman\Services\DualPointsManager;
use MistrFachman\Services\DomainConfigurationService;

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
     * @var DualPointsManager|null
     */
    private ?DualPointsManager $dualPointsManager = null;

    /**
     * Get or create DualPointsManager instance
     */
    protected function getDualPointsManager(): DualPointsManager {
        if ($this->dualPointsManager === null) {
            $this->dualPointsManager = new DualPointsManager();
        }
        return $this->dualPointsManager;
    }

    /**
     * Get the post type slug for this domain (English, for internal logic)
     */
    abstract protected function getPostType(): string;

    /**
     * Get the WordPress post type slug (Czech, for database/WP operations)
     */
    abstract protected function getWordPressPostType(): string;

    /**
     * Get the display name for this domain
     */
    abstract protected function getDomainDisplayName(): string;

    /**
     * Get the calculated points for this domain
     * Can be static (like Realizace) or dynamic (like Faktury)
     */
    abstract protected function getCalculatedPoints(int $post_id = 0): int;

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
        
        // If the field was empty, populate with calculated value.
        if ($points_to_award <= 0) {
            $points_to_award = $this->getCalculatedPoints($post_id);
            $this->set_points($post_id, $points_to_award);
            
            error_log("[{$this->getPostType()}:EDITOR] Auto-populated calculated points: {$points_to_award} for post {$post_id}");
        }

        error_log("[{$this->getPostType()}:EDITOR] Processing post editor save for post {$post_id} with {$points_to_award} points");
        
        $this->award_points($post_id, $user_id, $points_to_award);
    }

    /**
     * The PUBLIC service for awarding points - now uses DualPointsManager for atomic transactions.
     * Handles point differences and audit trail updates for both spendable and leaderboard points.
     */
    public function award_points(int $post_id, int $user_id, int $points_to_award): bool {
        if (!$this->validate_points_value($points_to_award)) {
            error_log("[{$this->getPostType()}:ERROR] Invalid points value '{$points_to_award}' for post {$post_id}");
            return false;
        }

        $points_already_awarded = (int)get_post_meta($post_id, DomainConfigurationService::getFieldName($this->getPostType(), 'awarded_points_meta'), true);
        $point_difference = $points_to_award - $points_already_awarded;

        // Only process if there's a change
        if ($point_difference !== 0) {
            $dual_manager = $this->getDualPointsManager();
            
            if ($point_difference > 0) {
                // Award additional points
                $log_message = $this->get_log_message($post_id, 'award', $point_difference);
                $result = $dual_manager->awardDualPoints(
                    $user_id,
                    $point_difference,
                    $this->getMyCredReference(),
                    $log_message,
                    $post_id
                );
            } else {
                // Revoke excess points (negative difference)
                $points_to_revoke = abs($point_difference);
                $log_message = $this->get_log_message($post_id, 'save', $point_difference);
                $result = $dual_manager->revokeDualPoints(
                    $user_id,
                    $points_to_revoke,
                    $this->getMyCredReference(),
                    $log_message,
                    $post_id
                );
            }
            
            if ($result) {
                update_post_meta($post_id, "_{$this->getWordPressPostType()}_points_awarded", $points_to_award);
                
                if ($point_difference > 0) {
                    error_log(sprintf(
                        '[%s:INFO] Dual points awarded for post %d: +%d points (total: %d)',
                        strtoupper($this->getPostType()),
                        $post_id,
                        $point_difference,
                        $points_to_award
                    ));
                } else {
                    error_log(sprintf(
                        '[%s:INFO] Dual points adjusted for post %d: %d points (total: %d)',
                        strtoupper($this->getPostType()),
                        $post_id,
                        $point_difference,
                        $points_to_award
                    ));
                }
                return true;
            } else {
                $operation = $point_difference > 0 ? 'award' : 'revoke';
                error_log(sprintf(
                    '[%s:ERROR] Failed to %s dual points for post %d: %+d points',
                    strtoupper($this->getPostType()),
                    $operation,
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
     * Revoke points using DualPointsManager with different policies for each point type
     */
    protected function revoke_points(int $post_id, int $user_id, string $new_status): void {
        $points_awarded = (int)get_post_meta($post_id, DomainConfigurationService::getFieldName($this->getPostType(), 'awarded_points_meta'), true);
        
        if ($points_awarded > 0) {
            // Get post title for better user experience
            $post_title = get_the_title($post_id) ?: "{$this->getDomainDisplayName()} #{$post_id}";
            $description = sprintf('Odebrání bodů: %s změněna na stav "%s"', $post_title, $this->get_status_label($new_status));
            
            // Use DualPointsManager for coordinated dual-point revocation
            $dual_manager = $this->getDualPointsManager();
            $result = $dual_manager->revokeDualPoints(
                $user_id,
                $points_awarded,
                $this->getMyCredReference(),
                $description,
                $post_id
            );
            
            if ($result) {
                // Get actual revoked amounts for tracking
                $balances = $dual_manager->getUserBalances($user_id);
                
                // For spendable points, we need to calculate how much was actually revoked
                // due to "no debt policy". For leaderboard points, the full amount is always revoked.
                $spendable_revoked = min($points_awarded, $balances['spendable'] + $points_awarded) - $balances['spendable'];
                
                // Update tracking to reflect actual points revoked from spendable balance
                // Note: Leaderboard points are tracked separately by myCred
                update_post_meta($post_id, "_{$this->getWordPressPostType()}_points_awarded", $points_awarded - $spendable_revoked);
                
                error_log("[{$this->getPostType()}:INFO] Dual points revoked from post {$post_id}: spendable={$spendable_revoked}/{$points_awarded}, leaderboard={$points_awarded}/{$points_awarded} (status: {$new_status})");
            } else {
                error_log("[{$this->getPostType()}:ERROR] Failed to revoke dual points for post {$post_id}");
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