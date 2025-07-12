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
     * Initialize hooks for this points handler
     */
    public function init_hooks(): void {
        parent::init_hooks();
        
        // Observer hook: React to changes in invoice_value field
        // DISABLED: AdminController ACF hooks handle this more reliably
        // add_action('updated_post_meta', [$this, 'on_invoice_value_update'], 15, 4);
    }

    /**
     * Observes changes to post meta and triggers points recalculation
     * when the 'invoice_value' field is updated. This method uses the
     * $meta_value parameter directly to avoid stale data issues.
     *
     * @param int    $meta_id     ID of the meta data entry.
     * @param int    $post_id     Post ID.
     * @param string $meta_key    Meta key.
     * @param mixed  $meta_value  New meta value.
     */
    public function on_invoice_value_update(int $meta_id, int $post_id, string $meta_key, $meta_value): void {
        // Step 1: Guard clause. Exit if this is not the field or post type we care about.
        if ($meta_key !== FakturaFieldService::getValueFieldSelector() || get_post_type($post_id) !== $this->getPostType()) {
            return;
        }

        // Step 2: Calculate points directly from the fresh meta_value (not from database)
        $new_invoice_value = (int) $meta_value;
        $new_points = $new_invoice_value > 0 ? (int) floor($new_invoice_value / 10) : 0;
        
        // Step 3: Get the currently stored points to see if an update is needed.
        $current_points = FakturaFieldService::getPoints($post_id);

        DebugLogger::logRecalculation('invoice', $post_id, [
            'trigger' => 'on_invoice_value_update',
            'meta_key' => $meta_key,
            'invoice_value' => $new_invoice_value,
            'points_calculated' => $new_points,
            'points_current' => $current_points,
            'update_needed' => $new_points !== $current_points,
            'hook_context' => 'updated_post_meta'
        ]);

        // Step 4: Only update if the calculated points are different from what's stored.
        if ($new_points !== $current_points) {
            // No need for loop prevention here, as we are not re-triggering the same meta_key update.
            $success = FakturaFieldService::setPoints($post_id, $new_points);

            DebugLogger::logFieldUpdate('invoice', $post_id, [
                'field_updated' => 'points',
                'old_value' => $current_points,
                'new_value' => $new_points,
                'success' => $success,
                'trigger_source' => 'invoice_value_change'
            ]);
        }
    }

    /**
     * Override the parent handle_post_save method.
     * The logic within is now handled by the more reliable on_invoice_value_update observer.
     * This prevents the parent's logic from running and conflicting with our observer.
     */
    public function handle_post_save(int $post_id, \WP_Post $post): void {
        // Do nothing. All logic is now in the reactive observer.
        DebugLogger::log('handle_post_save() called but disabled for Faktury - observer pattern handles all logic.', [
            'post_id' => $post_id,
            'post_status' => $post->post_status
        ]);
    }

    /**
     * Override handle_editor_save to support pending posts with ACF fields
     * 
     * Faktury form submission creates pending posts, but we still want to populate
     * the points field when ACF data is saved, even for pending posts.
     */
    public function handle_editor_save($post_id): void {
        $post_id = (int)$post_id;

        // Prevent this from running during an AJAX request, which has its own logic.
        if (wp_doing_ajax()) {
            DebugLogger::logPointsFlow('editor_save_skip', 'invoice', $post_id, [
                'reason' => 'wp_doing_ajax() returned true',
                'source' => 'handle_editor_save'
            ]);
            return;
        }

        // Verify this is the correct post type
        if (get_post_type($post_id) !== $this->getPostType()) {
            return;
        }

        $post_status = get_post_status($post_id);
        
        // For Faktury, we allow points calculation for both publish AND pending posts
        // This enables form submission workflow where posts are created as pending
        if (!in_array($post_status, ['publish', 'pending'])) {
            DebugLogger::logPointsFlow('editor_save_skip', 'invoice', $post_id, [
                'reason' => 'post_status not publish or pending',
                'post_status' => $post_status,
                'source' => 'handle_editor_save'
            ]);
            return;
        }

        $user_id = (int)get_post_field('post_author', $post_id);
        if (!$user_id || !get_userdata($user_id)) {
            DebugLogger::logPointsFlow('editor_save_skip', 'invoice', $post_id, [
                'reason' => 'invalid user_id',
                'user_id' => $user_id,
                'source' => 'handle_editor_save'
            ]);
            return;
        }

        // Get current points - if empty, populate with calculated value
        $current_points = FakturaFieldService::getPoints($post_id);
        
        DebugLogger::logPointsFlow('editor_save_start', 'invoice', $post_id, [
            'post_status' => $post_status,
            'user_id' => $user_id,
            'current_points' => $current_points,
            'source' => 'handle_editor_save'
        ]);
        
        if ($current_points === 0) {
            $calculated_points = $this->getCalculatedPoints($post_id);
            
            if ($calculated_points > 0) {
                $set_result = FakturaFieldService::setPoints($post_id, $calculated_points);
                
                DebugLogger::logFieldUpdate('invoice', $post_id, [
                    'field_updated' => 'points',
                    'action' => 'auto_populate',
                    'calculated_points' => $calculated_points,
                    'set_result' => $set_result,
                    'source' => 'handle_editor_save'
                ]);
                
                // Verify the value was actually saved
                $verified_points = FakturaFieldService::getPoints($post_id);
                if ($verified_points !== $calculated_points) {
                    DebugLogger::logFieldUpdate('invoice', $post_id, [
                        'error' => 'Points verification failed after auto-populate',
                        'expected' => $calculated_points,
                        'actual' => $verified_points,
                        'source' => 'handle_editor_save'
                    ]);
                }
            }
        }

        // Award points for both publish transitions and published posts
        // This covers both initial publish and subsequent updates
        if ($post_status === 'publish') {
            $points_to_award = FakturaFieldService::getPoints($post_id);
            
            if ($points_to_award > 0) {
                // Check if points were already awarded to prevent duplicates
                $awarded_points_meta = get_post_meta($post_id, FakturaFieldService::getAwardedPointsMetaFieldName(), true);
                $duplicate_prevention = !empty($awarded_points_meta) && (int)$awarded_points_meta === $points_to_award;
                
                DebugLogger::logAssignment('invoice', $post_id, [
                    'points_to_award' => $points_to_award,
                    'user_id' => $user_id,
                    'awarded_points_meta' => $awarded_points_meta,
                    'duplicate_prevention' => $duplicate_prevention,
                    'source' => 'handle_editor_save'
                ]);
                
                if (!$duplicate_prevention) {
                    $success = $this->award_points($post_id, $user_id, $points_to_award);
                    
                    if ($success) {
                        // Track awarded points to prevent duplicates
                        update_post_meta($post_id, FakturaFieldService::getAwardedPointsMetaFieldName(), $points_to_award);
                        
                        DebugLogger::logAssignment('invoice', $post_id, [
                            'points_awarded' => $points_to_award,
                            'user_id' => $user_id,
                            'success' => true,
                            'meta_updated' => true,
                            'source' => 'handle_editor_save'
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Revoke points with detailed logging for faktury
     * Overrides parent to add domain-specific logging
     * Matches base class signature: (int $post_id, int $user_id, string $new_status): void
     */
    protected function revoke_points(int $post_id, int $user_id, string $new_status): void {
        $invoice_value = FakturaFieldService::getValue($post_id);
        
        error_log("[FAKTURY:INFO] Revoking points from user {$user_id} for faktura {$post_id} (value: {$invoice_value} CZK, new status: {$new_status})");
        
        parent::revoke_points($post_id, $user_id, $new_status);
    }
}