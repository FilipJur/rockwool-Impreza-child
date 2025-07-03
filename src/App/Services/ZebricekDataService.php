<?php

declare(strict_types=1);

namespace MistrFachman\Services;

use MistrFachman\Services\PointTypeConstants;

/**
 * ZebricekDataService - Centralized Leaderboard Data Operations
 *
 * Provides shared myCred database operations for both leaderboard and position calculations.
 * Eliminates code duplication between shortcode classes while maintaining consistent caching.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZebricekDataService {
    private const CACHE_KEY_PREFIX = 'zebricek_data_';
    private const CACHE_DURATION = 600; // 10 minutes

    /**
     * Find the actual myCred log table name (handles case sensitivity)
     */
    public function find_mycred_log_table(): ?string
    {
        global $wpdb;
        
        // Check common variations
        $variations = [
            $wpdb->prefix . 'mycred_log',
            $wpdb->prefix . 'myCRED_log',
            $wpdb->prefix . 'mycred_Log',
            $wpdb->prefix . 'MYCRED_LOG'
        ];
        
        foreach ($variations as $table_name) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if ($exists) {
                return $table_name;
            }
        }
        
        // If not found, search all tables
        $tables = $wpdb->get_col("SHOW TABLES");
        foreach ($tables as $table) {
            if (stripos($table, 'mycred') !== false && stripos($table, 'log') !== false) {
                return $table;
            }
        }
        
        return null;
    }

    /**
     * Get leaderboard data for current year
     */
    public function get_leaderboard_data(int $limit = 30, int $offset = 0, string $year = ''): array
    {
        $year = $year ?: date('Y');
        $cache_key = self::CACHE_KEY_PREFIX . "leaderboard_{$year}_{$limit}_{$offset}";
        
        // Try cache first
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false && is_array($cached_data)) {
            return $cached_data;
        }

        // Generate fresh data
        $data = $this->get_leaderboard_data_from_db($limit, $offset, $year);
        
        // Cache the result
        set_transient($cache_key, $data, self::CACHE_DURATION);
        
        return $data;
    }

    /**
     * Get user's annual leaderboard points
     */
    public function get_user_annual_points(int $user_id, string $year = ''): int
    {
        $year = $year ?: date('Y');
        $cache_key = self::CACHE_KEY_PREFIX . "annual_{$year}_{$user_id}";
        
        // Try cache first
        $cached_points = get_transient($cache_key);
        if ($cached_points !== false) {
            return (int)$cached_points;
        }

        // Generate fresh data
        $points = $this->get_user_annual_points_from_db($user_id, $year);
        
        // Cache the result
        set_transient($cache_key, $points, self::CACHE_DURATION);
        
        return $points;
    }

    /**
     * Get user's position in leaderboard
     */
    public function get_user_position(int $user_id, string $year = ''): int
    {
        $year = $year ?: date('Y');
        $cache_key = self::CACHE_KEY_PREFIX . "position_{$year}_{$user_id}";
        
        // Try cache first
        $cached_position = get_transient($cache_key);
        if ($cached_position !== false) {
            return (int)$cached_position;
        }

        // Get user's points for the year
        $user_points = $this->get_user_annual_points($user_id, $year);
        
        if ($user_points === 0) {
            return 999; // Not ranked
        }

        // Calculate position
        $position = $this->calculate_user_position_from_db($user_id, $user_points, $year);
        
        // Cache the result
        set_transient($cache_key, $position, self::CACHE_DURATION);
        
        return $position;
    }

    /**
     * Get user's total leaderboard points balance
     */
    public function get_user_total_leaderboard_points(int $user_id): int
    {
        if (function_exists('mycred_get_users_balance')) {
            return (int)mycred_get_users_balance($user_id, PointTypeConstants::getLeaderboardPointType());
        }
        
        return (int)get_user_meta($user_id, 'leaderboard_points_balance', true);
    }

    /**
     * Clear all zebricek-related cache
     */
    public static function clear_cache(): void
    {
        global $wpdb;
        
        // Delete all zebricek transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_KEY_PREFIX . '%'
        ));
        
        // Also clear timeout transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . self::CACHE_KEY_PREFIX . '%'
        ));
    }

    /**
     * Clear cache for specific user
     */
    public static function clear_user_cache(int $user_id): void
    {
        global $wpdb;
        
        // Delete user-specific transients (user_id is now at the end)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::CACHE_KEY_PREFIX . '%_' . $user_id
        ));
        
        // Also clear timeout transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . self::CACHE_KEY_PREFIX . '%_' . $user_id
        ));
    }

    /**
     * Get leaderboard data from database
     */
    private function get_leaderboard_data_from_db(int $limit, int $offset, string $year): array
    {
        global $wpdb;
        
        $log_table = $this->find_mycred_log_table();
        if (!$log_table) {
            return [];
        }
        
        // Convert to Unix timestamps
        $year_start = strtotime($year . '-01-01 00:00:00');
        $year_end = strtotime(($year + 1) . '-01-01 00:00:00');
        
        // Query leaderboard_points specifically using ctype filter - calculate NET points (awards - revocations)
        $query = $wpdb->prepare("
            SELECT 
                log.user_id,
                u.display_name,
                SUM(log.creds) as total_points
            FROM {$log_table} log
            INNER JOIN {$wpdb->users} u ON log.user_id = u.ID
            WHERE log.time >= %d
                AND log.time < %d
                AND log.ctype = %s
            GROUP BY log.user_id
            HAVING total_points > 0
            ORDER BY total_points DESC
            LIMIT %d OFFSET %d
        ", $year_start, $year_end, PointTypeConstants::getLeaderboardPointType(), $limit, $offset);
        
        $results = $wpdb->get_results($query);
        
        if ($wpdb->last_error) {
            error_log("[ZebricekDataService] Database error: " . $wpdb->last_error);
            return [];
        }
        
        $users = [];
        if ($results) {
            foreach ($results as $user_data) {
                $user_id = (int)$user_data->user_id;
                $points = (int)$user_data->total_points;
                
                $users[] = [
                    'user_id' => $user_id,
                    'display_name' => $user_data->display_name,
                    'first_name' => get_user_meta($user_id, 'first_name', true) ?: '',
                    'last_name' => get_user_meta($user_id, 'last_name', true) ?: '',
                    'company' => get_user_meta($user_id, 'billing_company', true) ?: 'Samostatný řemeslník',
                    'points' => $points,
                    'formatted_points' => number_format($points, 0, ',', ' ') . ' b.'
                ];
            }
        }

        return $users;
    }

    /**
     * Get user's annual points from database
     */
    private function get_user_annual_points_from_db(int $user_id, string $year): int
    {
        global $wpdb;
        
        $log_table = $this->find_mycred_log_table();
        if (!$log_table) {
            // Fallback to total balance
            return $this->get_user_total_leaderboard_points($user_id);
        }
        
        // Convert to Unix timestamps
        $year_start = strtotime($year . '-01-01 00:00:00');
        $year_end = strtotime(($year + 1) . '-01-01 00:00:00');
        
        // Query leaderboard_points specifically using ctype filter - calculate NET points (awards - revocations)
        $query = $wpdb->prepare("
            SELECT COALESCE(SUM(creds), 0) as annual_points
            FROM {$log_table}
            WHERE user_id = %d 
            AND time >= %d
            AND time < %d
            AND ctype = %s
        ", $user_id, $year_start, $year_end, PointTypeConstants::getLeaderboardPointType());
        
        $annual_points = $wpdb->get_var($query);
        
        return max(0, (int)$annual_points);
    }

    /**
     * Calculate user's position from database
     */
    private function calculate_user_position_from_db(int $user_id, int $user_points, string $year): int
    {
        global $wpdb;
        
        $log_table = $this->find_mycred_log_table();
        if (!$log_table) {
            return 999;
        }
        
        // Convert to Unix timestamps
        $year_start = strtotime($year . '-01-01 00:00:00');
        $year_end = strtotime(($year + 1) . '-01-01 00:00:00');
        
        $query = $wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) + 1 as position
            FROM (
                SELECT user_id, SUM(creds) as total_points
                FROM {$log_table}
                WHERE time >= %d
                    AND time < %d
                    AND ctype = %s
                GROUP BY user_id
                HAVING total_points > %d
            ) as better_users
        ", $year_start, $year_end, PointTypeConstants::getLeaderboardPointType(), $user_points);
        
        $position = $wpdb->get_var($query);
        
        return max(1, (int)$position);
    }
}