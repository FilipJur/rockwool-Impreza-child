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
 * KEY BUSINESS RULE: Dynamic points calculation = floor(invoice_value / 30)
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
     * Get the post type slug for this domain
     */
    protected function getPostType(): string {
        return 'faktura';
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
        return 'approval_of_faktura';
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
     * Dynamic calculation: floor(invoice_value / 30)
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
        
        // CORE BUSINESS LOGIC: floor(value / 30)
        $calculated_points = (int) floor($invoice_value / 30);
        
        error_log("[FAKTURY:DEBUG] Points calculation for post {$post_id}: {$invoice_value} CZK / 30 = {$calculated_points} points");
        
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