<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\PointsHandlerBase;

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
     * Dynamic calculation: floor(invoice_value / 10) - 10 CZK = 1 bod
     * This is the key differentiator from Realizace (which has fixed 2500 points)
     *
     * @param int $post_id Post ID
     * @return int Calculated points
     */
    public function getCalculatedPoints(int $post_id = 0): int {
        if ($post_id === 0) {
            error_log('[FAKTURY:WARNING] getCalculatedPoints called with post_id 0');
            return 0;
        }
        
        $invoice_value = FakturaFieldService::getValue($post_id);
        
        if ($invoice_value <= 0) {
            error_log("[FAKTURY:WARNING] Invalid invoice value for post {$post_id}: {$invoice_value}");
            return 0;
        }
        
        // CORE BUSINESS LOGIC: floor(value / 10) - 10 CZK = 1 bod
        $calculated_points = (int) floor($invoice_value / 10);
        
        error_log("[FAKTURY:DEBUG] Points calculation for post {$post_id}: {$invoice_value} CZK / 10 = {$calculated_points} points");
        
        return $calculated_points;
    }

    /**
     * Award points with detailed logging for faktury
     * Overrides parent to add domain-specific logging
     */
    public function award_points(int $post_id, int $user_id, int $points_to_award): bool {
        $invoice_value = FakturaFieldService::getValue($post_id);
        
        error_log("[FAKTURY:INFO] Awarding {$points_to_award} points to user {$user_id} for faktura {$post_id} (value: {$invoice_value} CZK)");
        
        return parent::award_points($post_id, $user_id, $points_to_award);
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
            return;
        }

        $user_id = (int)get_post_field('post_author', $post_id);
        if (!$user_id || !get_userdata($user_id)) {
            return;
        }

        // Get current points - if empty, populate with calculated value
        $current_points = FakturaFieldService::getPoints($post_id);
        
        error_log("[FAKTURY:DEBUG] ACF hook firing for post {$post_id} - current points: {$current_points}, status: {$post_status}");
        
        if ($current_points === 0) {
            $calculated_points = $this->getCalculatedPoints($post_id);
            
            if ($calculated_points > 0) {
                $set_result = FakturaFieldService::setPoints($post_id, $calculated_points);
                
                error_log("[FAKTURY:DEBUG] Auto-populated points via ACF hook: {$calculated_points} for post #{$post_id} (result: " . ($set_result ? 'success' : 'failed') . ")");
                
                // Verify the value was actually saved
                $verified_points = FakturaFieldService::getPoints($post_id);
                error_log("[FAKTURY:DEBUG] Verification: points field now contains: {$verified_points}");
            }
        }

        // Only award points if the post is published (not pending)
        if ($post_status === 'publish') {
            $points_to_award = FakturaFieldService::getPoints($post_id);
            
            if ($points_to_award > 0) {
                $this->award_points($post_id, $user_id, $points_to_award);
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