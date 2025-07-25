<?php

declare(strict_types=1);

namespace MistrFachman\Services;

use MistrFachman\Services\PointTypeConstants;
use MistrFachman\Services\DebugLogger;
use MistrFachman\Services\DomainConfigurationService;

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
     * Get user's next target user to overtake in leaderboard
     * 
     * @param int $user_id User ID
     * @param string $year Year to check (defaults to current year)
     * @param int|null $user_points Optional pre-fetched user points to avoid redundant queries
     * @param int|null $user_position Optional pre-fetched user position to avoid redundant queries
     */
    public function get_user_next_target(int $user_id, string $year = '', ?int $user_points = null, ?int $user_position = null): ?array
    {
        $year = $year ?: date('Y');
        
        // Get user's current position and points (use cached values if provided)
        $user_position = $user_position ?? $this->get_user_position($user_id, $year);
        $user_points = $user_points ?? $this->get_user_annual_points($user_id, $year);
        
        if ($user_position <= 1) {
            DebugLogger::logToFile('zebricek', 'User already at top position', [
                'user_id' => $user_id,
                'position' => $user_position,
                'year' => $year
            ]);
            return null; // Already at the top
        }
        
        // Get the user at position above current user
        $target_position = $user_position - 1;
        
        // Get leaderboard data for that position
        $leaderboard_data = $this->get_leaderboard_data(1, $target_position - 1, $year);
        
        if (empty($leaderboard_data)) {
            DebugLogger::logToFile('zebricek', 'No target user found', [
                'user_id' => $user_id,
                'target_position' => $target_position,
                'year' => $year
            ]);
            return null;
        }
        
        $target_user = $leaderboard_data[0];
        $target_points = (int) $target_user['points'];
        
        // Calculate points needed to reach target user's level (tie)
        // To overtake, user needs exactly this many points to match the target
        $points_needed = max(0, $target_points - $user_points);
        
        DebugLogger::logToFile('zebricek', 'Next target calculated', [
            'user_id' => $user_id,
            'target_user_id' => $target_user['user_id'],
            'target_position' => $target_position,
            'user_points' => $user_points,
            'target_points' => $target_points,
            'points_needed' => $points_needed,
            'year' => $year
        ]);
        
        return [
            'position' => $target_position,
            'user_id' => $target_user['user_id'],
            'display_name' => $target_user['display_name'],
            'company' => $target_user['company'],
            'points' => $target_points,
            'points_needed' => $points_needed
        ];
    }
    
    /**
     * Get user's submission counts for approved realizations and invoices
     */
    public function get_user_submission_counts(int $user_id, string $year = ''): array
    {
        $year = $year ?: date('Y');
        $cache_key = self::CACHE_KEY_PREFIX . "submissions_{$year}_{$user_id}";
        
        // Try cache first
        $cached_counts = get_transient($cache_key);
        if ($cached_counts !== false && is_array($cached_counts)) {
            return $cached_counts;
        }
        
        // Get fresh counts from database
        $realizations_count = $this->get_user_approved_posts_count($user_id, 'realization', $year);
        $invoices_count = $this->get_user_approved_posts_count($user_id, 'invoice', $year);
        
        $counts = [
            'realizations_count' => $realizations_count,
            'invoices_count' => $invoices_count,
            'total_submissions' => $realizations_count + $invoices_count,
            'year' => $year
        ];
        
        // Cache the result
        set_transient($cache_key, $counts, self::CACHE_DURATION);
        
        DebugLogger::logToFile('zebricek', 'Submission counts calculated', [
            'user_id' => $user_id,
            'year' => $year,
            'counts' => $counts
        ]);
        
        return $counts;
    }
    
    /**
     * Get user's rank context (position + surrounding users)
     */
    public function get_user_rank_context(int $user_id, string $year = ''): array
    {
        $year = $year ?: date('Y');
        
        $position = $this->get_user_position($user_id, $year);
        $points = $this->get_user_annual_points($user_id, $year);
        
        // Get users around this position (1 above, 1 below)
        $above_user = null;
        $below_user = null;
        
        if ($position > 1) {
            $above_data = $this->get_leaderboard_data(1, $position - 2, $year);
            $above_user = !empty($above_data) ? $above_data[0] : null;
        }
        
        $below_data = $this->get_leaderboard_data(1, $position, $year);
        $below_user = !empty($below_data) ? $below_data[0] : null;
        
        $context = [
            'current_position' => $position,
            'current_points' => $points,
            'above_user' => $above_user,
            'below_user' => $below_user,
            'year' => $year
        ];
        
        DebugLogger::logToFile('zebricek', 'Rank context calculated', [
            'user_id' => $user_id,
            'context' => $context
        ]);
        
        return $context;
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
        
        DebugLogger::logToFile('zebricek', 'User cache cleared', [
            'user_id' => $user_id,
            'cache_prefix' => self::CACHE_KEY_PREFIX
        ]);
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
     * FIXED: Now properly excludes deleted users by joining with wp_users table
     */
    private function calculate_user_position_from_db(int $user_id, int $user_points, string $year): int
    {
        global $wpdb;
        
        $log_table = $this->find_mycred_log_table();
        if (!$log_table) {
            DebugLogger::logToFile('zebricek', 'Position calculation failed: myCred log table not found', [
                'user_id' => $user_id,
                'year' => $year
            ]);
            return 999;
        }
        
        // Convert to Unix timestamps
        $year_start = strtotime($year . '-01-01 00:00:00');
        $year_end = strtotime(($year + 1) . '-01-01 00:00:00');
        
        // FIXED: Add INNER JOIN with wp_users to exclude deleted users
        $query = $wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) + 1 as position
            FROM (
                SELECT log.user_id, SUM(log.creds) as total_points
                FROM {$log_table} log
                INNER JOIN {$wpdb->users} u ON log.user_id = u.ID
                WHERE log.time >= %d
                    AND log.time < %d
                    AND log.ctype = %s
                GROUP BY log.user_id
                HAVING total_points > %d
            ) as better_users
        ", $year_start, $year_end, PointTypeConstants::getLeaderboardPointType(), $user_points);
        
        $position = $wpdb->get_var($query);
        
        // DEBUG: Get all users with leaderboard points for this year
        $debug_query = $wpdb->prepare("
            SELECT log.user_id, u.display_name, SUM(log.creds) as total_points
            FROM {$log_table} log
            INNER JOIN {$wpdb->users} u ON log.user_id = u.ID
            WHERE log.time >= %d
                AND log.time < %d
                AND log.ctype = %s
            GROUP BY log.user_id
            ORDER BY total_points DESC
        ", $year_start, $year_end, PointTypeConstants::getLeaderboardPointType());
        
        $all_users = $wpdb->get_results($debug_query);
        
        DebugLogger::logToFile('zebricek', 'Position calculated', [
            'user_id' => $user_id,
            'user_points' => $user_points,
            'year' => $year,
            'position' => $position,
            'query' => $query,
            'all_users_with_points' => $all_users,
            'point_type_used' => PointTypeConstants::getLeaderboardPointType()
        ]);
        
        return max(1, (int)$position);
    }
    
    /**
     * Get count of approved posts for user in given year
     * Uses existing domain configuration for post types
     */
    private function get_user_approved_posts_count(int $user_id, string $domain, string $year): int
    {
        $post_type = DomainConfigurationService::getWordPressPostType($domain);
        
        // Query approved posts for the year
        $query_args = [
            'post_type' => $post_type,
            'author' => $user_id,
            'post_status' => 'publish', // Only approved posts count
            'posts_per_page' => -1, // Get all
            'fields' => 'ids', // Only need count, not full posts
            'date_query' => [
                [
                    'year' => $year,
                ],
            ],
        ];
        
        $query = new \WP_Query($query_args);
        $count = $query->found_posts;
        
        DebugLogger::logToFile('zebricek', 'Approved posts count calculated', [
            'user_id' => $user_id,
            'domain' => $domain,
            'post_type' => $post_type,
            'year' => $year,
            'count' => $count
        ]);
        
        return $count;
    }
}