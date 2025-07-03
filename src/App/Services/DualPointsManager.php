<?php

declare(strict_types=1);

namespace MistrFachman\Services;

use MistrFachman\Services\PointTypeConstants;

/**
 * DualPointsManager - Atomic Dual Point Type Management
 *
 * Handles synchronized awarding/revoking of both spendable and leaderboard points
 * with compensating transaction logic to prevent data inconsistency.
 *
 * CORE PRINCIPLES:
 * - Single Responsibility: Only handles dual-point transactions
 * - Atomic Operations: All-or-nothing approach with rollback on failure
 * - Separate Policies: Spendable (no debt) vs Leaderboard (full revocation)
 * - Audit Trail: Separate myCred references for clear logging
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DualPointsManager {

    /**
     * Award both spendable and leaderboard points atomically
     *
     * @param int $user_id User ID receiving points
     * @param int $points Points to award (same amount for both types)
     * @param string $spendable_reference myCred reference for spendable points
     * @param string $description Human-readable description
     * @param int $post_id Associated post ID
     * @return bool Success status
     */
    public function awardDualPoints(
        int $user_id,
        int $points,
        string $spendable_reference,
        string $description,
        int $post_id
    ): bool {
        if (!$this->validateMyCred()) {
            return false;
        }

        if ($points <= 0) {
            DebugLogger::logToFile('dual_points', 'AWARD_VALIDATION_FAILED', [
                'reason' => 'Invalid points amount',
                'user_id' => $user_id,
                'points' => $points,
                'post_id' => $post_id
            ]);
            return false;
        }

        DebugLogger::logToFile('dual_points', 'AWARD_DUAL_POINTS_START', [
            'user_id' => $user_id,
            'points' => $points,
            'spendable_reference' => $spendable_reference,
            'post_id' => $post_id
        ]);

        // Step 1: Award spendable points
        $spendable_result = mycred_add(
            $spendable_reference,
            $user_id,
            $points,
            $description,
            $post_id,
            [],
            PointTypeConstants::getDefaultPointType()
        );

        if (!$spendable_result) {
            DebugLogger::logToFile('dual_points', 'AWARD_SPENDABLE_FAILED', [
                'user_id' => $user_id,
                'points' => $points,
                'reference' => $spendable_reference,
                'post_id' => $post_id
            ]);
            return false;
        }

        // Step 2: Award leaderboard points
        $leaderboard_reference = 'leaderboard_' . $spendable_reference;
        $leaderboard_description = $description . ' (Žebříček)';
        
        $leaderboard_result = mycred_add(
            $leaderboard_reference,
            $user_id,
            $points,
            $leaderboard_description,
            $post_id,
            [],
            PointTypeConstants::getLeaderboardPointType()
        );

        if (!$leaderboard_result) {
            // COMPENSATING TRANSACTION: Rollback spendable points
            $rollback_result = mycred_add(
                $spendable_reference . '_rollback',
                $user_id,
                -$points,
                'Rollback: Leaderboard points failed - ' . $description,
                $post_id,
                [],
                PointTypeConstants::getDefaultPointType()
            );

            DebugLogger::logToFile('dual_points', 'AWARD_LEADERBOARD_FAILED_ROLLBACK', [
                'user_id' => $user_id,
                'points' => $points,
                'post_id' => $post_id,
                'rollback_success' => $rollback_result,
                'leaderboard_reference' => $leaderboard_reference
            ]);

            return false;
        }

        DebugLogger::logToFile('dual_points', 'AWARD_DUAL_POINTS_SUCCESS', [
            'user_id' => $user_id,
            'points' => $points,
            'spendable_reference' => $spendable_reference,
            'leaderboard_reference' => $leaderboard_reference,
            'post_id' => $post_id
        ]);

        return true;
    }

    /**
     * Revoke both spendable and leaderboard points with different policies
     *
     * @param int $user_id User ID to revoke points from
     * @param int $points Points to revoke
     * @param string $spendable_reference myCred reference for spendable points
     * @param string $description Human-readable description
     * @param int $post_id Associated post ID
     * @return bool Success status
     */
    public function revokeDualPoints(
        int $user_id,
        int $points,
        string $spendable_reference,
        string $description,
        int $post_id
    ): bool {
        if (!$this->validateMyCred()) {
            return false;
        }

        if ($points <= 0) {
            DebugLogger::logToFile('dual_points', 'REVOKE_VALIDATION_FAILED', [
                'reason' => 'Invalid points amount',
                'user_id' => $user_id,
                'points' => $points,
                'post_id' => $post_id
            ]);
            return false;
        }

        DebugLogger::logToFile('dual_points', 'REVOKE_DUAL_POINTS_START', [
            'user_id' => $user_id,
            'points' => $points,
            'spendable_reference' => $spendable_reference,
            'post_id' => $post_id
        ]);

        $results = [];

        // Step 1: Revoke spendable points with "no debt policy"
        $current_balance = (int)mycred_get_users_balance($user_id, PointTypeConstants::getDefaultPointType());
        $spendable_to_revoke = min($points, $current_balance);

        if ($spendable_to_revoke > 0) {
            $spendable_result = mycred_add(
                'revoke_' . $spendable_reference,
                $user_id,
                -$spendable_to_revoke,
                $description,
                $post_id,
                [],
                PointTypeConstants::getDefaultPointType()
            );

            $results['spendable'] = $spendable_result;

            DebugLogger::logToFile('dual_points', 'REVOKE_SPENDABLE_EXECUTED', [
                'user_id' => $user_id,
                'points_requested' => $points,
                'points_revoked' => $spendable_to_revoke,
                'current_balance' => $current_balance,
                'success' => $spendable_result,
                'post_id' => $post_id
            ]);
        } else {
            DebugLogger::logToFile('dual_points', 'REVOKE_SPENDABLE_SKIPPED_NO_DEBT', [
                'user_id' => $user_id,
                'points_requested' => $points,
                'current_balance' => $current_balance,
                'post_id' => $post_id
            ]);
        }

        // Step 2: FULLY revoke leaderboard points (ignore balance)
        $leaderboard_reference = 'revoke_leaderboard_' . $spendable_reference;
        $leaderboard_description = $description . ' (Žebříček)';

        $leaderboard_result = mycred_add(
            $leaderboard_reference,
            $user_id,
            -$points,
            $leaderboard_description,
            $post_id,
            [],
            PointTypeConstants::getLeaderboardPointType()
        );

        $results['leaderboard'] = $leaderboard_result;

        DebugLogger::logToFile('dual_points', 'REVOKE_LEADERBOARD_EXECUTED', [
            'user_id' => $user_id,
            'points_revoked' => $points,
            'success' => $leaderboard_result,
            'reference' => $leaderboard_reference,
            'post_id' => $post_id
        ]);

        $overall_success = !in_array(false, $results, true);

        DebugLogger::logToFile('dual_points', 'REVOKE_DUAL_POINTS_COMPLETE', [
            'user_id' => $user_id,
            'points' => $points,
            'post_id' => $post_id,
            'spendable_revoked' => $spendable_to_revoke,
            'leaderboard_revoked' => $points,
            'overall_success' => $overall_success,
            'results' => $results
        ]);

        return $overall_success;
    }

    /**
     * Get current balances for both point types
     *
     * @param int $user_id User ID
     * @return array Associative array with 'spendable' and 'leaderboard' balances
     */
    public function getUserBalances(int $user_id): array {
        if (!$this->validateMyCred()) {
            return ['spendable' => 0, 'leaderboard' => 0];
        }

        return [
            'spendable' => (int)mycred_get_users_balance($user_id, PointTypeConstants::getDefaultPointType()),
            'leaderboard' => (int)mycred_get_users_balance($user_id, PointTypeConstants::getLeaderboardPointType())
        ];
    }

    /**
     * Check if the leaderboard point type is properly registered
     *
     * @return bool True if leaderboard point type exists
     */
    public function isLeaderboardPointTypeRegistered(): bool {
        // Check if point type exists in myCred types option
        $types = get_option('mycred_types', []);
        if (!isset($types[PointTypeConstants::getLeaderboardPointType()])) {
            return false;
        }

        // Additional check using myCred function if available
        if (function_exists('mycred_get_types')) {
            $point_types = mycred_get_types();
            return isset($point_types[PointTypeConstants::getLeaderboardPointType()]);
        }

        return true;
    }

    /**
     * Validate myCred availability and configuration
     *
     * @return bool True if myCred is available and configured
     */
    private function validateMyCred(): bool {
        if (!function_exists('mycred_add')) {
            DebugLogger::logToFile('dual_points', 'MYCRED_NOT_AVAILABLE', [
                'functions_checked' => ['mycred_add'],
                'error' => 'myCred plugin not loaded or functions not available'
            ]);
            return false;
        }

        if (!$this->isLeaderboardPointTypeRegistered()) {
            DebugLogger::logToFile('dual_points', 'LEADERBOARD_POINT_TYPE_NOT_REGISTERED', [
                'point_type' => PointTypeConstants::getLeaderboardPointType(),
                'error' => 'Leaderboard point type not registered in myCred'
            ]);
            return false;
        }

        return true;
    }
}