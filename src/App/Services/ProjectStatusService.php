<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Project Status Service
 *
 * Centralized service for all project status-related logic across realizace and faktury domains.
 * Consolidates duplicate code and provides single source of truth for status handling.
 *
 * Features:
 * - Status configuration (labels, colors, metadata)
 * - User project detection with caching
 * - Standardized database queries
 * - Smart cache invalidation on status transitions
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ProjectStatusService
{
    /**
     * Cache group for project status data
     */
    private const CACHE_GROUP = 'project_status';
    
    /**
     * Cache key prefix for user project data
     */
    private const USER_CACHE_PREFIX = 'user_projects_';
    
    /**
     * Cache key prefix for user aggregate status
     */
    private const AGGREGATE_CACHE_PREFIX = 'user_aggregate_';
    
    /**
     * Cache duration in seconds (5 minutes)
     */
    private const CACHE_DURATION = 300;

    /**
     * Get project post types dynamically from DomainConfigurationService
     * Replaces hardcoded PROJECT_POST_TYPES constant for architectural compliance
     * 
     * @return array WordPress post type slugs
     */
    private static function getProjectPostTypes(): array
    {
        $domains = DomainConfigurationService::getRegisteredDomains();
        return array_map(
            fn($domain) => DomainConfigurationService::getWordPressPostType($domain),
            $domains
        );
    }

    /**
     * Status configuration registry
     */
    private static array $status_config = [
        'pending' => [
            'label' => 'Čeká na schválení',
            'colors' => [
                'border' => 'border-yellow-200',
                'badge' => 'bg-yellow-100 text-yellow-800'
            ],
            'description' => 'Submitted, awaiting admin review'
        ],
        'publish' => [
            'label' => 'Schváleno',
            'colors' => [
                'border' => 'border-green-200',
                'badge' => 'bg-green-100 text-green-800'
            ],
            'description' => 'Approved by admin'
        ],
        'rejected' => [
            'label' => 'Odmítnuto',
            'colors' => [
                'border' => 'border-red-200',
                'badge' => 'bg-red-100 text-red-800'
            ],
            'description' => 'Rejected by admin'
        ],
        'draft' => [
            'label' => 'Koncept',
            'colors' => [
                'border' => 'border-gray-200',
                'badge' => 'bg-gray-100 text-gray-800'
            ],
            'description' => 'Work in progress (rarely used for projects)'
        ]
    ];

    /**
     * Get complete status configuration
     *
     * @param string $status Status slug
     * @return array Status configuration
     * @throws \InvalidArgumentException If status not found
     */
    public static function getStatusConfig(string $status): array
    {
        if (!isset(self::$status_config[$status])) {
            throw new \InvalidArgumentException("Unknown status: {$status}");
        }

        return self::$status_config[$status];
    }

    /**
     * Get all valid project statuses
     *
     * @return array Status slugs
     */
    public static function getAllValidStatuses(): array
    {
        return array_keys(self::$status_config);
    }

    /**
     * Get localized status label
     *
     * @param string $status Status slug
     * @return string Localized label
     */
    public static function getStatusLabel(string $status): string
    {
        try {
            return self::getStatusConfig($status)['label'];
        } catch (\InvalidArgumentException $e) {
            return $status; // Fallback to raw status
        }
    }

    /**
     * Get Tailwind CSS classes for status display
     *
     * @param string $status Status slug
     * @return array Border and badge color classes
     */
    public static function getStatusColors(string $status): array
    {
        try {
            return self::getStatusConfig($status)['colors'];
        } catch (\InvalidArgumentException $e) {
            // Fallback to gray for unknown statuses
            return [
                'border' => 'border-gray-200',
                'badge' => 'bg-gray-100 text-gray-800'
            ];
        }
    }

    /**
     * Check if status is valid
     *
     * @param string $status Status to validate
     * @return bool True if valid
     */
    public static function isValidStatus(string $status): bool
    {
        return isset(self::$status_config[$status]);
    }

    /**
     * Get user's aggregate project status with caching
     *
     * @param int $user_id User ID
     * @return string Aggregate status: 'no_projects', 'has_pending', 'has_published', 'has_rejected'
     */
    public function getUserProjectStatus(int $user_id): string
    {
        $cache_key = self::AGGREGATE_CACHE_PREFIX . $user_id;
        
        // Clear cache in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            delete_transient($cache_key);
        }

        $status = get_transient($cache_key);
        if ($status !== false) {
            return $status;
        }

        $status = $this->calculateUserAggregateStatus($user_id);
        set_transient($cache_key, $status, self::CACHE_DURATION);

        return $status;
    }

    /**
     * Check if user has any published projects
     *
     * @param int $user_id User ID
     * @return bool True if user has published projects
     */
    public function hasPublishedProjects(int $user_id): bool
    {
        $projects = $this->getProjectsForUser($user_id, ['publish']);
        return !empty($projects);
    }

    /**
     * Check if user has any pending projects
     *
     * @param int $user_id User ID
     * @return bool True if user has pending projects
     */
    public function hasPendingProjects(int $user_id): bool
    {
        $projects = $this->getProjectsForUser($user_id, ['pending']);
        return !empty($projects);
    }

    /**
     * Check if user has any rejected projects
     *
     * @param int $user_id User ID
     * @return bool True if user has rejected projects
     */
    public function hasRejectedProjects(int $user_id): bool
    {
        $projects = $this->getProjectsForUser($user_id, ['rejected']);
        return !empty($projects);
    }

    /**
     * Check if user has uploaded first project (any status except none)
     *
     * @param int $user_id User ID
     * @return bool True if user has uploaded any project
     */
    public function hasUploadedFirstProject(int $user_id): bool
    {
        $projects = $this->getProjectsForUser($user_id, ['pending', 'publish', 'rejected']);
        return !empty($projects);
    }

    /**
     * Check if user has published first project
     *
     * @param int $user_id User ID
     * @return bool True if user has published projects
     */
    public function hasPublishedFirstProject(int $user_id): bool
    {
        return $this->hasPublishedProjects($user_id);
    }

    /**
     * Get cached project data for user with progress percentage
     *
     * @param int $user_id User ID
     * @return array Project data with progress info
     */
    public function getCachedUserProjects(int $user_id): array
    {
        $cache_key = self::USER_CACHE_PREFIX . $user_id;
        
        // Clear cache in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            delete_transient($cache_key);
        }

        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $data = [
            'has_uploaded' => $this->hasUploadedFirstProject($user_id),
            'has_published' => $this->hasPublishedFirstProject($user_id),
            'has_rejected' => $this->hasRejectedProjects($user_id),
            'progress_percentage' => $this->getProjectProgressPercentage($user_id)
        ];

        set_transient($cache_key, $data, self::CACHE_DURATION);

        return $data;
    }

    /**
     * Get project upload progress percentage
     *
     * @param int $user_id User ID
     * @return int Progress percentage (0-100)
     */
    public function getProjectProgressPercentage(int $user_id): int
    {
        // Check for published projects first (100%)
        if ($this->hasPublishedProjects($user_id)) {
            return 100;
        }

        // Check for pending projects (75%)
        if ($this->hasPendingProjects($user_id)) {
            return 75;
        }

        // Check for rejected projects (50% - needs revision)
        if ($this->hasRejectedProjects($user_id)) {
            return 50;
        }

        // Check for any draft projects (25%)
        $drafts = $this->getProjectsForUser($user_id, ['draft']);
        if (!empty($drafts)) {
            return 25;
        }

        // No projects yet
        return 0;
    }

    /**
     * Get projects for user with optional status filtering
     *
     * @param int $user_id User ID
     * @param array|null $statuses Status filter (null for all)
     * @param array $post_types Post types to include
     * @return array WP_Post objects
     */
    public function getProjectsForUser(int $user_id, ?array $statuses = null, ?array $post_types = null): array
    {
        $post_types = $post_types ?: self::getProjectPostTypes();
        $statuses = $statuses ?: self::getAllValidStatuses();

        $query_args = [
            'post_type' => $post_types,
            'author' => $user_id,
            'post_status' => $statuses,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'suppress_filters' => true
        ];

        return get_posts($query_args);
    }

    /**
     * Get projects by status across all users
     *
     * @param string $status Status to filter by
     * @param int|null $user_id Optional user filter
     * @param array $post_types Post types to include
     * @return array WP_Post objects
     */
    public function getProjectsByStatus(string $status, ?int $user_id = null, ?array $post_types = null): array
    {
        if (!self::isValidStatus($status)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $post_types = $post_types ?: self::getProjectPostTypes();

        $query_args = [
            'post_type' => $post_types,
            'post_status' => $status,
            'posts_per_page' => -1,
            'suppress_filters' => true
        ];

        if ($user_id) {
            $query_args['author'] = $user_id;
        }

        return get_posts($query_args);
    }

    /**
     * Calculate user's aggregate project status
     *
     * @param int $user_id User ID
     * @return string Aggregate status
     */
    private function calculateUserAggregateStatus(int $user_id): string
    {
        // Check for published projects first
        if ($this->hasPublishedProjects($user_id)) {
            return 'has_published';
        }

        // Check for pending projects
        if ($this->hasPendingProjects($user_id)) {
            return 'has_pending';
        }

        // Check for rejected projects
        if ($this->hasRejectedProjects($user_id)) {
            return 'has_rejected';
        }

        // No projects uploaded yet
        return 'no_projects';
    }

    /**
     * Invalidate user cache when project status changes
     * Hook this to 'transition_post_status'
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param \WP_Post $post Post object
     */
    public static function invalidateUserCache(string $new_status, string $old_status, \WP_Post $post): void
    {
        // Only invalidate for project post types
        if (!in_array($post->post_type, self::getProjectPostTypes(), true)) {
            return;
        }

        $user_id = $post->post_author;
        
        // Clear both cache keys for the user
        delete_transient(self::USER_CACHE_PREFIX . $user_id);
        delete_transient(self::AGGREGATE_CACHE_PREFIX . $user_id);
    }

    /**
     * Register WordPress hooks for cache invalidation
     */
    public static function registerHooks(): void
    {
        add_action('transition_post_status', [self::class, 'invalidateUserCache'], 10, 3);
    }
}