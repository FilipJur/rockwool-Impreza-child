<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

use MistrFachman\Base\PointsHandlerBase;

/**
 * Realizace Points Handler - myCred Integration
 *
 * Core business logic that connects ACF fields to myCred points.
 * Handles point awards and corrections based on realizace approval status.
 * 
 * Now extends PointsHandlerBase with fixed 2500 points default.
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
        return 'realization';
    }

    /**
     * Get the WordPress post type slug (English, aligned with database)
     */
    protected function getWordPressPostType(): string {
        return 'realization';
    }

    /**
     * Get the display name for this domain
     */
    protected function getDomainDisplayName(): string {
        return 'Realizace';
    }

    /**
     * Get the calculated points for realizace
     * Uses PointsCalculationService for centralized calculation logic
     */
    public function getCalculatedPoints(int $post_id = 0): int {
        // Use centralized points calculation service
        return \MistrFachman\Services\PointsCalculationService::calculatePoints('realization', $post_id);
    }

    /**
     * Get the ACF field selector that stores points
     * Delegates to centralized field service
     */
    protected function getPointsFieldSelector(): string {
        return RealizaceFieldService::getPointsFieldSelector();
    }

    /**
     * Get the myCred reference type for this domain
     */
    protected function getMyCredReference(): string {
        return 'approval_of_realization';
    }

    /**
     * Get the awarding hook configuration for Realizace domain
     * Uses wp_after_insert_post which runs after all meta operations complete
     */
    protected function get_awarding_hook(): array {
        return [
            'name' => 'wp_after_insert_post',
            'priority' => 10,
            'accepted_args' => 4
        ];
    }
}