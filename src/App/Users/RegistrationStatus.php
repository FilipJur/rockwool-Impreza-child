<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Registration Status Constants
 *
 * Single source of truth for all user registration status values.
 * Centralizes status management and provides validation methods.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RegistrationStatus {

    /**
     * User needs to complete the final registration form
     */
    public const NEEDS_FORM = 'needs_form';

    /**
     * User has submitted form and is awaiting admin review
     */
    public const AWAITING_REVIEW = 'awaiting_review';

    /**
     * User has been approved (legacy status, rarely used)
     */
    public const APPROVED = 'approved';

    /**
     * Get all valid registration statuses
     *
     * @return array<string> Array of valid status constants
     */
    public static function getAllStatuses(): array {
        return [
            self::NEEDS_FORM,
            self::AWAITING_REVIEW,
            self::APPROVED,
        ];
    }

    /**
     * Check if a status is valid
     *
     * @param string $status Status to validate
     * @return bool True if status is valid
     */
    public static function isValidStatus(string $status): bool {
        return in_array($status, self::getAllStatuses(), true);
    }

    /**
     * Get human-readable display name for status
     *
     * @param string $status Status constant
     * @return string Localized display name
     */
    public static function getDisplayName(string $status): string {
        return match ($status) {
            self::NEEDS_FORM => 'Vyplnit formulář',
            self::AWAITING_REVIEW => 'Čeká na schválení',
            self::APPROVED => 'Schváleno',
            default => 'Neznámý stav'
        };
    }

    /**
     * Get CSS class for status styling
     *
     * @param string $status Status constant
     * @return string CSS class name
     */
    public static function getCssClass(string $status): string {
        return match ($status) {
            self::NEEDS_FORM => 'status-needs-form',
            self::AWAITING_REVIEW => 'status-awaiting-review',
            self::APPROVED => 'status-approved',
            default => 'status-unknown'
        };
    }

    /**
     * Get status priority for sorting (lower = higher priority)
     *
     * @param string $status Status constant
     * @return int Priority value
     */
    public static function getPriority(string $status): int {
        return match ($status) {
            self::NEEDS_FORM => 1,
            self::AWAITING_REVIEW => 2,
            self::APPROVED => 3,
            default => 999
        };
    }
}