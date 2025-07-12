<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\PointsHandlerBase;
use MistrFachman\Services\DebugLogger;

/**
 * Faktury Points Handler - myCred Integration
 *
 * Core business logic that connects ACF fields to myCred points.
 * Handles point awards and corrections based on faktura approval status.
 * 
 * KEY BUSINESS RULE: Dynamic points calculation = floor(invoice_value / 10) - 10 CZK = 1 bod
 * This is the core requirement that differentiates Faktury from Realizace.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PointsHandler extends PointsHandlerBase {

    /**
     * Get the post type slug for this domain (English, for internal logic)
     */
    protected function getPostType(): string {
        return 'invoice';
    }

    /**
     * Get the WordPress post type slug (English, aligned with database)
     */
    protected function getWordPressPostType(): string {
        return 'invoice';
    }

    /**
     * Get the display name for this domain
     */
    protected function getDomainDisplayName(): string {
        return 'Faktura';
    }

    /**
     * Get the myCred reference for this domain
     */
    protected function getMyCredReference(): string {
        return 'approval_of_invoice';
    }

    /**
     * Get the points field selector
     */
    protected function getPointsFieldSelector(): string {
        return FakturaFieldService::getPointsFieldSelector();
    }

    /**
     * Get the calculated points for faktury - CORE BUSINESS LOGIC
     * 
     * Uses PointsCalculationService for centralized calculation logic
     * Dynamic calculation: floor(invoice_value / 10) - 10 CZK = 1 bod
     * This is the key differentiator from Realizace (which has fixed 2500 points)
     *
     * @param int $post_id Post ID
     * @return int Calculated points
     */
    public function getCalculatedPoints(int $post_id = 0): int {
        if ($post_id === 0) {
            DebugLogger::logCalculation('invoice', 0, [
                'error' => 'getCalculatedPoints called with post_id 0',
                'points_calculated' => 0
            ]);
            return 0;
        }
        
        $invoice_value = FakturaFieldService::getValue($post_id);
        
        // Use centralized points calculation service
        $calculated_points = \MistrFachman\Services\PointsCalculationService::calculatePoints('invoice', $post_id);
        
        DebugLogger::logCalculation('invoice', $post_id, [
            'invoice_value' => $invoice_value,
            'points_calculated' => $calculated_points,
            'calculation_formula' => 'floor(invoice_value / 10)',
            'source' => 'PointsCalculationService'
        ]);
        
        return $calculated_points;
    }

    /**
     * Award points with detailed logging for faktury
     * Overrides parent to add domain-specific logging
     */
    public function award_points(int $post_id, int $user_id, int $points_to_award): bool {
        $invoice_value = FakturaFieldService::getValue($post_id);
        
        DebugLogger::logAssignment('invoice', $post_id, [
            'points_awarded' => $points_to_award,
            'user_id' => $user_id,
            'invoice_value' => $invoice_value,
            'method' => 'award_points',
            'source' => 'PointsHandler::award_points'
        ]);
        
        $success = parent::award_points($post_id, $user_id, $points_to_award);
        
        if (!$success) {
            DebugLogger::logAssignment('invoice', $post_id, [
                'error' => 'Failed to award points via parent::award_points',
                'points_attempted' => $points_to_award,
                'user_id' => $user_id,
                'success' => false
            ]);
        }
        
        return $success;
    }

    /**
     * Initialize hooks for this points handler - Final ACF Priority Solution
     * Uses sequential ACF hook priorities to guarantee correct timing
     */
    public function init_hooks(): void {
        // DON'T use base class hooks - they have wp_after_insert_post race condition
        // Instead, use sequential ACF priorities for guaranteed execution order
        
        // Priority 20: Calculate points after ACF saves field data
        add_action('acf/save_post', [$this, 'recalculate_points_after_save'], 20);
        
        // Priority 25: Award points after calculation is complete
        add_action('acf/save_post', [$this, 'handle_awarding_after_calculation'], 25);
        
        // Status transition hook: Handle revocations when posts are unpublished
        add_action('transition_post_status', [$this, 'handle_status_transition'], 10, 3);
        
        // Keep deletion handling from base class
        add_action('before_delete_post', [$this, 'handle_permanent_deletion'], 5);
        
        DebugLogger::logPointsFlow('faktury_hooks_initialized', 'invoice', 0, [
            'message' => 'Faktury PointsHandler using hybrid approach - ACF priorities + status transitions',
            'hooks' => [
                'acf/save_post at priority 20 (calculation)',
                'acf/save_post at priority 25 (awarding)',
                'transition_post_status at priority 10 (revocations)',
                'before_delete_post at priority 5 (deletion)'
            ],
            'removed_hooks' => ['wp_after_insert_post (race condition)'],
            'solution' => 'ACF priorities for awarding + status transitions for revocations',
            'source' => 'Faktury\\PointsHandler::init_hooks (Hybrid fix)'
        ]);
    }

    /**
     * Recalculate points after ACF save completes - Gemini Fix
     * Runs after all form fields are saved to avoid race condition
     */
    public function recalculate_points_after_save($post_id): void {
        $post_id = (int) $post_id;
        
        // Guard: Only process invoice posts
        if (get_post_type($post_id) !== $this->getWordPressPostType()) {
            return;
        }

        // Guard: Prevent infinite loops (important when using update_field in acf/save_post)
        static $processing = [];
        if (isset($processing[$post_id])) {
            return;
        }
        $processing[$post_id] = true;

        // Get the just-saved invoice value
        $invoice_value = FakturaFieldService::getValue($post_id);
        $calculated_points = $invoice_value > 0 ? (int) floor($invoice_value / 10) : 0;
        $current_points = FakturaFieldService::getPoints($post_id);

        DebugLogger::logPointsFlow('acf_save_post_hook', 'invoice', $post_id, [
            'invoice_value' => $invoice_value,
            'calculated_points' => $calculated_points,
            'current_points' => $current_points,
            'update_needed' => $calculated_points !== $current_points,
            'source' => 'PointsHandler::recalculate_points_after_save (Gemini fix)'
        ]);

        // Update points field only if changed
        if ($calculated_points !== $current_points) {
            $success = FakturaFieldService::setPoints($post_id, $calculated_points);
            
            DebugLogger::logFieldUpdate('invoice', $post_id, [
                'field_updated' => 'points',
                'old_value' => $current_points,
                'new_value' => $calculated_points,
                'success' => $success,
                'trigger_source' => 'acf_save_post_after_completion',
                'source' => 'PointsHandler::recalculate_points_after_save'
            ]);
        }

        // Clean up processing flag
        unset($processing[$post_id]);
    }

    /**
     * Handle awarding after calculation completes - Final ACF Priority Solution
     * Runs at acf/save_post priority 25, after calculation at priority 20
     * This guarantees the "points to assign" field has the correct calculated value
     */
    public function handle_awarding_after_calculation($post_id): void {
        $post_id = (int) $post_id;
        
        // Guard: Only process invoice posts
        if (get_post_type($post_id) !== $this->getWordPressPostType()) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $user_id = (int) $post->post_author;
        if (!$user_id || !get_userdata($user_id)) {
            return;
        }

        // Get state tracking meta field - mirrors PointsHandlerBase logic
        $last_awarded_meta_key = $this->getLastAwardedPointsMetaKey();
        $last_awarded_points = (int) get_post_meta($post_id, $last_awarded_meta_key, true);

        // Handle based on current post status
        if ($post->post_status === 'publish') {
            // PUBLISHED POST: Award/adjust points based on current calculated value
            // At this point, recalculate_points_after_save() has already run and updated the field
            $current_points = $this->getCalculatedPoints($post_id);
            
            DebugLogger::logPointsFlow('acf_awarding_check', 'invoice', $post_id, [
                'current_points' => $current_points,
                'last_awarded_points' => $last_awarded_points,
                'points_changed' => $current_points !== $last_awarded_points,
                'post_status' => $post->post_status,
                'user_id' => $user_id,
                'hook_used' => 'acf/save_post priority 25',
                'source' => 'PointsHandler::handle_awarding_after_calculation (Final fix)'
            ]);

            // Award/adjust points if they've changed
            if ($current_points !== $last_awarded_points) {
                $trigger = $last_awarded_points === 0 ? 'initial_publish_after_calculation' : 'field_update_after_calculation';
                
                DebugLogger::logAssignment($this->getPostType(), $post_id, [
                    'operation' => 'acf_priority_point_adjustment',
                    'old_points' => $last_awarded_points,
                    'new_points' => $current_points,
                    'point_difference' => $current_points - $last_awarded_points,
                    'trigger' => $trigger,
                    'source' => 'PointsHandler::handle_awarding_after_calculation'
                ]);

                $success = $this->award_points($post_id, $user_id, $current_points);
                
                if ($success) {
                    update_post_meta($post_id, $last_awarded_meta_key, $current_points);
                    
                    DebugLogger::logAssignment($this->getPostType(), $post_id, [
                        'operation' => 'state_tracking_updated',
                        'last_awarded_points' => $current_points,
                        'meta_key' => $last_awarded_meta_key,
                        'source' => 'PointsHandler::handle_awarding_after_calculation'
                    ]);
                }
            }
        }
        
        // NOTE: Revocation logic removed from here - now handled by handle_status_transition()
        // This method only handles awarding for published posts
    }

    /**
     * Handle status transitions - Hybrid Solution for Revocations
     * Only handles revocations when posts are unpublished
     * Awarding is handled by ACF priority sequence
     */
    public function handle_status_transition(string $new_status, string $old_status, \WP_Post $post): void {
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
        $last_awarded_points = (int) get_post_meta($post->ID, $last_awarded_meta_key, true);

        DebugLogger::logPointsFlow('status_transition_check', 'invoice', $post->ID, [
            'old_status' => $old_status,
            'new_status' => $new_status,
            'last_awarded_points' => $last_awarded_points,
            'will_revoke' => ($old_status === 'publish' && $new_status !== 'publish' && $last_awarded_points > 0),
            'user_id' => $user_id,
            'source' => 'PointsHandler::handle_status_transition (Hybrid approach)'
        ]);

        // REVOCATION: 'publish' → Any other status
        if ($old_status === 'publish' && $new_status !== 'publish') {
            if ($last_awarded_points > 0) {
                DebugLogger::logAssignment($this->getPostType(), $post->ID, [
                    'operation' => 'status_transition_revoke',
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                    'points_to_revoke' => $last_awarded_points,
                    'user_id' => $user_id,
                    'source' => 'PointsHandler::handle_status_transition'
                ]);
                
                $this->revoke_points($post->ID, $user_id, $new_status);
                
                // Reset state tracking
                update_post_meta($post->ID, $last_awarded_meta_key, 0);
                
                DebugLogger::logAssignment($this->getPostType(), $post->ID, [
                    'operation' => 'state_tracking_reset',
                    'reason' => 'status_transition_revocation',
                    'meta_key' => $last_awarded_meta_key,
                    'source' => 'PointsHandler::handle_status_transition'
                ]);
            }
        }
        
        // NOTE: We do NOT handle awarding here anymore - that's handled by ACF priority sequence
        // This prevents race conditions while ensuring revocations work properly
    }

    /**
     * Handle post save - Phase 2 Refactor: Disabled
     * All awarding logic now handled by centralized status transition hook
     */
    public function handle_post_save(int $post_id, \WP_Post $post): void {
        DebugLogger::logPointsFlow('handle_post_save_disabled', 'invoice', $post_id, [
            'post_status' => $post->post_status,
            'message' => 'handle_post_save disabled - centralized status transition handles awarding',
            'source' => 'PointsHandler::handle_post_save (Phase 2 refactored)'
        ]);
    }

    // Gemini Fix: Removed problematic acf/update_value method
    // All calculation now handled by recalculate_points_after_save()

    /**
     * Phase 2 Refactor: Enhanced duplicate prevention method
     * Used by centralized status transition hook
     */
    protected function getAwardedPointsMetaKey(): string {
        return FakturaFieldService::getAwardedPointsMetaFieldName();
    }

    /**
     * Get last awarded points meta key - State-Aware Solution
     * Used for state tracking to detect field changes on published posts
     */
    protected function getLastAwardedPointsMetaKey(): string {
        return "_{$this->getWordPressPostType()}_last_awarded_points";
    }

    /**
     * Revoke points with detailed logging for faktury
     * Overrides parent to add domain-specific logging
     * Matches base class signature: (int $post_id, int $user_id, string $new_status): void
     */
    protected function revoke_points(int $post_id, int $user_id, string $new_status): void {
        $invoice_value = FakturaFieldService::getValue($post_id);
        
        DebugLogger::logPointsFlow('revoke_points_start', 'invoice', $post_id, [
            'user_id' => $user_id,
            'new_status' => $new_status,
            'invoice_value' => $invoice_value,
            'source' => 'PointsHandler::revoke_points'
        ]);
        
        parent::revoke_points($post_id, $user_id, $new_status);
    }

    /**
     * Handle permanent deletion of posts
     * Since we're not inheriting base class hooks, we need this method
     */
    public function handle_permanent_deletion(int $post_id): void {
        if (get_post_type($post_id) === $this->getWordPressPostType()) {
            $user_id = (int)get_post_field('post_author', $post_id);
            if ($user_id > 0) {
                $this->revoke_points($post_id, $user_id, 'deleted');
            }
        }
    }
}