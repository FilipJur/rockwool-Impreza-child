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
     * Get the awarding hook configuration for Faktury domain
     * Uses ACF save_post at priority 25 to ensure calculation completes first
     */
    protected function get_awarding_hook(): array {
        return [
            'name' => 'acf/save_post',
            'priority' => 25,
            'accepted_args' => 1
        ];
    }

    /**
     * Initialize domain-specific calculation hook
     * Called by Manager after base hooks are registered
     */
    public function init_domain_hooks(): void {
        // Add calculation hook at priority 20 (before awarding at 25)
        add_action('acf/save_post', [$this, 'recalculate_points_after_save'], 20);
        
        DebugLogger::logPointsFlow('faktury_domain_hooks_initialized', 'invoice', 0, [
            'message' => 'Faktury domain-specific hooks added',
            'hooks' => [
                'acf/save_post at priority 20 (calculation)',
            ],
            'note' => 'Base class handles awarding/revocation/deletion hooks',
            'source' => 'Faktury\\PointsHandler::init_domain_hooks'
        ]);
    }

    /**
     * Recalculate points after ACF save completes
     * Runs at priority 20, before awarding at priority 25
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

}