<?php

declare(strict_types=1);

namespace MistrFachman\Base;

use MistrFachman\Services\DualPointsManager;
use MistrFachman\Services\DomainConfigurationService;
use MistrFachman\Services\ProjectStatusService;
use MistrFachman\Services\DebugLogger;

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
     * Get the awarding hook configuration for this domain
     * Subclasses must define their preferred hook for awarding points
     * 
     * @return array{name: string, priority: int, accepted_args: int}
     */
    abstract protected function get_awarding_hook(): array;

    /**
     * Get the revocation hook configuration for this domain
     * Default: transition_post_status, but subclasses can override
     * 
     * @return array{name: string, priority: int, accepted_args: int}
     */
    protected function get_revocation_hook(): array {
        return [
            'name' => 'transition_post_status',
            'priority' => 10,
            'accepted_args' => 3
        ];
    }

    /**
     * Initialize hooks using Template Method Pattern
     * Subclasses define WHAT hooks to use, base class handles HOW to use them
     * This method is now FINAL to prevent bypassing
     */
    final public function init_hooks(): void {
        // Get awarding hook configuration from subclass
        $awarding_hook = $this->get_awarding_hook();
        add_action(
            $awarding_hook['name'], 
            [$this, 'handle_awarding_trigger'], 
            $awarding_hook['priority'], 
            $awarding_hook['accepted_args']
        );
        
        // Get revocation hook configuration (with default)
        $revocation_hook = $this->get_revocation_hook();
        add_action(
            $revocation_hook['name'], 
            [$this, 'handle_revocation_trigger'], 
            $revocation_hook['priority'], 
            $revocation_hook['accepted_args']
        );
        
        // Deletion handling remains consistent
        add_action('before_delete_post', [$this, 'handle_permanent_deletion'], 5);
        
        DebugLogger::logPointsFlow('template_method_hooks_initialized', $this->getPostType(), 0, [
            'awarding_hook' => $awarding_hook,
            'revocation_hook' => $revocation_hook,
            'deletion_hook' => ['name' => 'before_delete_post', 'priority' => 5],
            'pattern' => 'Template Method Pattern - subclass defines hooks, base class implements logic',
            'source' => 'PointsHandlerBase::init_hooks (Template Method refactor)'
        ]);
    }

    /**
     * Unified awarding trigger handler - routes different hook signatures
     * Extracts post_id from various hook argument patterns and delegates to core logic
     */
    public function handle_awarding_trigger(...$args): void {
        // Extract post_id based on hook type
        $hook_name = current_action();
        $post_id = 0;
        $post = null;
        
        // Handle different hook signatures
        if ($hook_name === 'wp_after_insert_post') {
            // Signature: int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before
            $post_id = $args[0] ?? 0;
            $post = $args[1] ?? null;
            $update = $args[2] ?? false;
            $post_before = $args[3] ?? null;
        } elseif ($hook_name === 'acf/save_post') {
            // Signature: int|string $post_id
            $post_id = is_numeric($args[0]) ? (int)$args[0] : 0;
            $post = $post_id > 0 ? get_post($post_id) : null;
            $update = true; // ACF save is always an update context
            $post_before = null; // Not available in ACF context
        } elseif ($hook_name === 'save_post') {
            // Signature: int $post_id, WP_Post $post, bool $update
            $post_id = $args[0] ?? 0;
            $post = $args[1] ?? null;
            $update = $args[2] ?? false;
            $post_before = null; // Not available in save_post
        }
        
        // Validate we have necessary data
        if (!$post_id || !$post) {
            return;
        }
        
        // Delegate to existing logic (which already handles post type checks)
        $this->handle_post_after_insert($post_id, $post, $update, $post_before);
    }

    /**
     * Unified revocation trigger handler - routes different hook signatures
     * Handles both transition_post_status and other potential revocation hooks
     */
    public function handle_revocation_trigger(...$args): void {
        $hook_name = current_action();
        
        if ($hook_name === 'transition_post_status') {
            // Signature: string $new_status, string $old_status, WP_Post $post
            $new_status = $args[0] ?? '';
            $old_status = $args[1] ?? '';
            $post = $args[2] ?? null;
            
            if (!$post || !is_a($post, 'WP_Post')) {
                return;
            }
            
            // Guard for correct post type
            if ($post->post_type !== $this->getWordPressPostType()) {
                return;
            }
            
            // Only handle revocations (publish -> other status)
            if ($old_status === 'publish' && $new_status !== 'publish') {
                $user_id = (int) $post->post_author;
                if (!$user_id || !get_userdata($user_id)) {
                    return;
                }
                
                $last_awarded_meta_key = $this->getLastAwardedPointsMetaKey();
                $last_awarded_points = (int) get_post_meta($post->ID, $last_awarded_meta_key, true);
                
                if ($last_awarded_points > 0) {
                    DebugLogger::logAssignment($this->getPostType(), $post->ID, [
                        'operation' => 'unified_revocation_trigger',
                        'old_status' => $old_status,
                        'new_status' => $new_status,
                        'points_to_revoke' => $last_awarded_points,
                        'user_id' => $user_id,
                        'source' => 'PointsHandlerBase::handle_revocation_trigger'
                    ]);
                    
                    $this->revoke_points($post->ID, $user_id, $new_status);
                    
                    // Reset state tracking
                    update_post_meta($post->ID, $last_awarded_meta_key, 0);
                }
            }
        }
        // Add other revocation hook handling here if needed in the future
    }

    /**
     * Award points with duplicate prevention - Phase 2 Refactor
     * Called by centralized status transition handler
     */
    protected function award_points_with_duplicate_check(int $post_id, int $user_id): void {
        // Get current points from the points field (should be calculated by ACF hooks)
        $points_on_post = $this->get_current_points($post_id);
        
        DebugLogger::logAssignment($this->getPostType(), $post_id, [
            'operation' => 'award_check_start',
            'points_on_post' => $points_on_post,
            'user_id' => $user_id,
            'source' => 'PointsHandlerBase::award_points_with_duplicate_check'
        ]);
        
        if ($points_on_post <= 0) {
            // If no points calculated, use getCalculatedPoints as fallback
            $points_on_post = $this->getCalculatedPoints($post_id);
            if ($points_on_post > 0) {
                $this->set_points($post_id, $points_on_post);
                DebugLogger::logCalculation($this->getPostType(), $post_id, [
                    'trigger' => 'fallback_calculation',
                    'calculated_points' => $points_on_post,
                    'reason' => 'Points field was empty, using calculated value',
                    'source' => 'PointsHandlerBase::award_points_with_duplicate_check'
                ]);
            }
        }

        if ($points_on_post > 0) {
            // Check for duplicate awarding - Phase 2 Critical Fix
            $awarded_meta_key = $this->getAwardedPointsMetaKey();
            $already_awarded = get_post_meta($post_id, $awarded_meta_key, true);
            // Prevent duplicate only if meta exists AND matches current points AND is not 0 (revoked)
            $duplicate_prevention = !empty($already_awarded) && (int)$already_awarded === $points_on_post && (int)$already_awarded > 0;
            
            DebugLogger::logAssignment($this->getPostType(), $post_id, [
                'operation' => 'duplicate_check',
                'points_to_award' => $points_on_post,
                'already_awarded' => $already_awarded,
                'duplicate_prevention' => $duplicate_prevention,
                'user_id' => $user_id,
                'source' => 'PointsHandlerBase::award_points_with_duplicate_check'
            ]);
            
            if (!$duplicate_prevention) {
                $success = $this->award_points($post_id, $user_id, $points_on_post);
                
                if ($success) {
                    // Mark as awarded to prevent duplicates
                    update_post_meta($post_id, $awarded_meta_key, $points_on_post);
                    
                    DebugLogger::logAssignment($this->getPostType(), $post_id, [
                        'operation' => 'award_success',
                        'points_awarded' => $points_on_post,
                        'user_id' => $user_id,
                        'meta_updated' => true,
                        'source' => 'PointsHandlerBase::award_points_with_duplicate_check'
                    ]);
                } else {
                    DebugLogger::logAssignment($this->getPostType(), $post_id, [
                        'operation' => 'award_failed',
                        'points_attempted' => $points_on_post,
                        'user_id' => $user_id,
                        'error' => 'award_points() returned false',
                        'source' => 'PointsHandlerBase::award_points_with_duplicate_check'
                    ]);
                }
            }
        } else {
            DebugLogger::logAssignment($this->getPostType(), $post_id, [
                'operation' => 'award_skipped',
                'reason' => 'No points to award (points_on_post <= 0)',
                'points_on_post' => $points_on_post,
                'user_id' => $user_id,
                'source' => 'PointsHandlerBase::award_points_with_duplicate_check'
            ]);
        }
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
     * Post-insert handler - Final Race Condition Fix
     * Uses wp_after_insert_post which runs AFTER all meta operations (including ACF) complete
     * This is the definitive solution to eliminate ACF calculation vs point awarding race conditions
     */
    public function handle_post_after_insert(int $post_id, \WP_Post $post, bool $update, ?\WP_Post $post_before): void {
        // Guard for correct post type
        if ($post->post_type !== $this->getWordPressPostType()) {
            return;
        }

        $user_id = (int) $post->post_author;
        if (!$user_id || !get_userdata($user_id)) {
            return;
        }

        // Get state tracking meta field
        $last_awarded_meta_key = $this->getLastAwardedPointsMetaKey();
        $last_awarded_points = (int) get_post_meta($post_id, $last_awarded_meta_key, true);

        // Handle based on current post status
        if ($post->post_status === 'publish') {
            // PUBLISHED POST: Award/adjust points based on current calculated value
            // ACF calculations are now guaranteed to be complete
            $current_points = $this->getCalculatedPoints($post_id);
            
            DebugLogger::logPointsFlow('post_after_insert_publish_check', $this->getPostType(), $post_id, [
                'current_points' => $current_points,
                'last_awarded_points' => $last_awarded_points,
                'points_changed' => $current_points !== $last_awarded_points,
                'is_update' => $update,
                'is_initial_publish' => $last_awarded_points === 0,
                'user_id' => $user_id,
                'hook_used' => 'wp_after_insert_post',
                'source' => 'PointsHandlerBase::handle_post_after_insert (Final fix)'
            ]);

            // Award/adjust points if they've changed
            if ($current_points !== $last_awarded_points) {
                $trigger = $last_awarded_points === 0 ? 'initial_publish_after_all_meta' : 'field_update_after_all_meta';
                
                DebugLogger::logAssignment($this->getPostType(), $post_id, [
                    'operation' => 'post_after_insert_point_adjustment',
                    'old_points' => $last_awarded_points,
                    'new_points' => $current_points,
                    'point_difference' => $current_points - $last_awarded_points,
                    'trigger' => $trigger,
                    'source' => 'PointsHandlerBase::handle_post_after_insert'
                ]);

                $success = $this->award_points($post_id, $user_id, $current_points);
                
                if ($success) {
                    update_post_meta($post_id, $last_awarded_meta_key, $current_points);
                    
                    DebugLogger::logAssignment($this->getPostType(), $post_id, [
                        'operation' => 'state_tracking_updated',
                        'last_awarded_points' => $current_points,
                        'meta_key' => $last_awarded_meta_key,
                        'source' => 'PointsHandlerBase::handle_post_after_insert'
                    ]);
                }
            }
            
        } else {
            // UNPUBLISHED POST: Revoke any previously awarded points
            if ($last_awarded_points > 0) {
                DebugLogger::logAssignment($this->getPostType(), $post_id, [
                    'operation' => 'unpublish_revoke',
                    'old_status' => $post_before ? $post_before->post_status : 'unknown',
                    'new_status' => $post->post_status,
                    'points_to_revoke' => $last_awarded_points,
                    'user_id' => $user_id,
                    'source' => 'PointsHandlerBase::handle_post_after_insert'
                ]);
                
                $this->revoke_points($post_id, $user_id, $post->post_status);
                
                // Reset state tracking
                update_post_meta($post_id, $last_awarded_meta_key, 0);
                
                DebugLogger::logAssignment($this->getPostType(), $post_id, [
                    'operation' => 'state_tracking_reset',
                    'reason' => 'post_unpublished',
                    'meta_key' => $last_awarded_meta_key,
                    'source' => 'PointsHandlerBase::handle_post_after_insert'
                ]);
            }
        }
    }

    /**
     * Legacy post update handler - REPLACED
     * This method has been replaced by handle_post_after_insert() to eliminate race conditions
     */
    public function handle_post_update(int $post_id, \WP_Post $post, bool $update): void {
        // Guard for correct post type
        if ($post->post_type !== $this->getWordPressPostType()) {
            return;
        }

        $user_id = (int) $post->post_author;
        if (!$user_id || !get_userdata($user_id)) {
            return;
        }

        // Get state tracking meta field
        $last_awarded_meta_key = $this->getLastAwardedPointsMetaKey();
        $last_awarded_points = (int) get_post_meta($post_id, $last_awarded_meta_key, true);

        // Handle based on current post status
        if ($post->post_status === 'publish') {
            // PUBLISHED POST: Award/adjust points based on current calculated value
            $current_points = $this->getCalculatedPoints($post_id);
            
            DebugLogger::logPointsFlow('state_aware_publish_check', $this->getPostType(), $post_id, [
                'current_points' => $current_points,
                'last_awarded_points' => $last_awarded_points,
                'points_changed' => $current_points !== $last_awarded_points,
                'is_update' => $update,
                'is_initial_publish' => $last_awarded_points === 0,
                'user_id' => $user_id,
                'source' => 'PointsHandlerBase::handle_post_update (consolidated awarding)'
            ]);

            // Award/adjust points if they've changed
            if ($current_points !== $last_awarded_points) {
                $trigger = $last_awarded_points === 0 ? 'initial_publish_after_acf' : 'field_value_change_on_published_post';
                
                DebugLogger::logAssignment($this->getPostType(), $post_id, [
                    'operation' => 'state_aware_point_adjustment',
                    'old_points' => $last_awarded_points,
                    'new_points' => $current_points,
                    'point_difference' => $current_points - $last_awarded_points,
                    'trigger' => $trigger,
                    'source' => 'PointsHandlerBase::handle_post_update'
                ]);

                $success = $this->award_points($post_id, $user_id, $current_points);
                
                if ($success) {
                    update_post_meta($post_id, $last_awarded_meta_key, $current_points);
                    
                    DebugLogger::logAssignment($this->getPostType(), $post_id, [
                        'operation' => 'state_tracking_updated',
                        'last_awarded_points' => $current_points,
                        'meta_key' => $last_awarded_meta_key,
                        'source' => 'PointsHandlerBase::handle_post_update'
                    ]);
                }
            }
            
        } else {
            // UNPUBLISHED POST: Revoke any previously awarded points
            if ($last_awarded_points > 0) {
                DebugLogger::logAssignment($this->getPostType(), $post_id, [
                    'operation' => 'unpublish_revoke',
                    'old_status' => 'publish',
                    'new_status' => $post->post_status,
                    'points_to_revoke' => $last_awarded_points,
                    'user_id' => $user_id,
                    'source' => 'PointsHandlerBase::handle_post_update'
                ]);
                
                $this->revoke_points($post_id, $user_id, $post->post_status);
                
                // Reset state tracking
                update_post_meta($post_id, $last_awarded_meta_key, 0);
                
                DebugLogger::logAssignment($this->getPostType(), $post_id, [
                    'operation' => 'state_tracking_reset',
                    'reason' => 'post_unpublished',
                    'meta_key' => $last_awarded_meta_key,
                    'source' => 'PointsHandlerBase::handle_post_update'
                ]);
            }
        }
    }

    /**
     * Legacy status transition handler - DISABLED
     * This method has been replaced by handle_post_update() to eliminate race conditions.
     * The handle_post_update() method now handles all status transitions after ACF field updates.
     */
    public function handle_status_transition(string $new_status, string $old_status, \WP_Post $post): void {
        // This method is intentionally disabled to prevent race conditions
        // All point operations are now handled by handle_post_update() which runs after ACF
        
        DebugLogger::logPointsFlow('status_transition_disabled', $this->getPostType(), $post->ID, [
            'old_status' => $old_status,
            'new_status' => $new_status,
            'message' => 'Status transition hook disabled - all logic moved to handle_post_update',
            'reason' => 'Race condition prevention',
            'source' => 'PointsHandlerBase::handle_status_transition (DISABLED)'
        ]);
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
                
                // Phase 2 Critical Fix: Set meta to 0 to indicate points were revoked
                // This allows proper duplicate prevention on republish
                update_post_meta($post_id, "_{$this->getWordPressPostType()}_points_awarded", 0);
                
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
     * Get current points stored on post - Phase 2 Refactor
     * Renamed for clarity in centralized system
     */
    protected function get_current_points(int $post_id): int {
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
     * Get awarded points meta key - Phase 2 Refactor
     * Centralized method for duplicate prevention
     */
    protected function getAwardedPointsMetaKey(): string {
        return "_{$this->getWordPressPostType()}_points_awarded";
    }

    /**
     * Get last awarded points meta key - Gemini State-Aware Solution
     * Used for state tracking to detect field changes on published posts
     */
    protected function getLastAwardedPointsMetaKey(): string {
        return "_{$this->getWordPressPostType()}_last_awarded_points";
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
     * Get localized status label using ProjectStatusService
     */
    protected function get_status_label(string $status): string {
        return ProjectStatusService::getStatusLabel($status);
    }
}