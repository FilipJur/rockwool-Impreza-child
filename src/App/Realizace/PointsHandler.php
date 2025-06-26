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
     * Get the WordPress post type slug (Czech, for database/WP operations)
     */
    protected function getWordPressPostType(): string {
        return 'realizace';
    }

    /**
     * Get the display name for this domain
     */
    protected function getDomainDisplayName(): string {
        return 'Realizace';
    }

    /**
     * Get the calculated points for realizace (fixed 2500)
     */
    public function getCalculatedPoints(int $post_id = 0): int {
        // Fixed value for Realizace domain - $post_id parameter not used
        return 2500;
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
        return 'approval_of_realizace';
    }
}