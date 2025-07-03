<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Point Type Constants Service
 *
 * Centralized source of truth for all myCred point type identifiers.
 * Eliminates code duplication and ensures consistency across the entire system.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PointTypeConstants
{
    /**
     * Default spendable points type used for e-commerce purchases
     */
    public const DEFAULT_POINT_TYPE = 'mycred_default';

    /**
     * Leaderboard points type used for competitive rankings
     * These points can be fully revoked and show net achievements
     */
    public const LEADERBOARD_POINT_TYPE = 'leaderboard_points';

    /**
     * Get all available point types
     *
     * @return array<string, string> Array of point type ID => description
     */
    public static function getAllPointTypes(): array
    {
        return [
            self::DEFAULT_POINT_TYPE => 'Spendable Points (e-commerce)',
            self::LEADERBOARD_POINT_TYPE => 'Leaderboard Points (competitive ranking)'
        ];
    }

    /**
     * Validate if a point type is supported
     *
     * @param string $point_type Point type to validate
     * @return bool True if supported, false otherwise
     */
    public static function isValidPointType(string $point_type): bool
    {
        return in_array($point_type, [
            self::DEFAULT_POINT_TYPE,
            self::LEADERBOARD_POINT_TYPE
        ], true);
    }

    /**
     * Get the default point type for spendable balance
     *
     * @return string Default point type identifier
     */
    public static function getDefaultPointType(): string
    {
        return self::DEFAULT_POINT_TYPE;
    }

    /**
     * Get the leaderboard point type for competitive rankings
     *
     * @return string Leaderboard point type identifier
     */
    public static function getLeaderboardPointType(): string
    {
        return self::LEADERBOARD_POINT_TYPE;
    }
}