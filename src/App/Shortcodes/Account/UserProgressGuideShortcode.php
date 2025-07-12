<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes\Account;

use MistrFachman\Shortcodes\ShortcodeBase;
use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;
use MistrFachman\Services\DomainConfigurationService;

/**
 * User Progress Guide Shortcode Component
 *
 * Displays user's registration and project upload progress with milestones.
 * Shows only for users who haven't made their first purchase yet.
 *
 * Usage: [user_progress_guide]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UserProgressGuideShortcode extends ShortcodeBase
{
    protected array $default_attributes = [
        'show_for_experienced' => 'false',
        'class' => ''
    ];

    public function get_tag(): string
    {
        return 'user_progress_guide';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '';
        }

        $user_id = get_current_user_id();

        // Check if user should see progress guide
        if (!$this->should_show_progress_guide($user_id, $attributes)) {
            return '';
        }

        // Get progress data
        $progress_data = $this->get_progress_data($user_id);

        // Prepare template data
        $template_data = [
            'progress_data' => $progress_data,
            'attributes' => $attributes,
            'wrapper_classes' => $this->get_wrapper_classes($attributes),
            'user_id' => $user_id
        ];

        return $this->render_progress_guide($template_data);
    }

    /**
     * Check if progress guide should be shown for user
     */
    private function should_show_progress_guide(int $user_id, array $attributes): bool
    {
        // Always show if admin forces it
        if ($attributes['show_for_experienced'] === 'true') {
            return true;
        }

        $user_status = $this->user_service->get_user_registration_status($user_id);

        // Always show for non-full members (registration not complete)
        if ($user_status === 'needs_form' || $user_status === 'awaiting_review') {
            return true;
        }

        // For full members, check if they have made first purchase
        if ($user_status === 'full_member') {
            $has_made_purchase = $this->ecommerce_manager->has_user_made_purchase($user_id);
            return !$has_made_purchase;
        }

        return false;
    }

    /**
     * Get user's progress data with caching optimization
     */
    private function get_progress_data(int $user_id): array
    {
        $user_status = $this->user_service->get_user_registration_status($user_id);

        // Registration step progress based on status
        $registration_completed = ($user_status === 'full_member');
        $registration_progress = DomainConfigurationService::getUserProgressPercentage($user_status);

        // Use cached project data to avoid repeated database queries
        $project_data = $this->get_cached_user_projects($user_id);

        // Check first project upload (only for full members)
        $first_project_uploaded = false;
        $project_progress = 0;
        $project_status = 'none'; // none, draft, pending, published

        if ($user_status === 'full_member') {
            $first_project_uploaded = $project_data['has_uploaded'];
            $project_progress = $first_project_uploaded ? DomainConfigurationService::PROGRESS_PROJECT_UPLOADED : $project_data['progress_percentage'];

            // Determine granular project status
            if ($project_data['has_uploaded']) {
                $project_status = $project_data['has_published'] ? 'published' : 'pending';
            } elseif ($project_data['progress_percentage'] > 0) {
                $project_status = 'draft';
            } else {
                $project_status = 'none';
            }

        }

        return [
            'user_status' => $user_status,
            'registration' => [
                'completed' => $registration_completed,
                'progress_percentage' => $registration_progress,
                'points' => $registration_completed ? DomainConfigurationService::getUserWorkflowReward('registration_completed') : 0
            ],
            'first_project' => [
                'completed' => $first_project_uploaded,
                'progress_percentage' => $project_progress,
                'points' => DomainConfigurationService::getUserWorkflowReward('first_project_uploaded'),
                'status' => $project_status
            ]
        ];
    }

    /**
     * Get cached user project data to avoid repeated database queries
     */
    private function get_cached_user_projects(int $user_id): array
    {
        $cache_key = "user_projects_status_{$user_id}";

        // Clear cache if in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            delete_transient($cache_key);
        }

        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $data = [
            'has_uploaded' => $this->has_uploaded_first_project($user_id),
            'has_published' => $this->has_published_first_project($user_id),
            'progress_percentage' => $this->get_project_progress_percentage($user_id)
        ];

        // Cache for 5 minutes
        set_transient($cache_key, $data, 300);

        return $data;
    }

    /**
     * Check if user has uploaded first project (including realizace and faktury)
     */
    private function has_uploaded_first_project(int $user_id): bool
    {
        $realizace = get_posts([
            'post_type' => 'realizace',
            'author' => $user_id,
            'post_status' => ['publish', 'pending'],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        $faktury = get_posts([
            'post_type' => 'faktura',
            'author' => $user_id,
            'post_status' => ['publish', 'pending'],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        return !empty($realizace) || !empty($faktury);
    }

    /**
     * Check if user has published first project (including realizace and faktury)
     */
    private function has_published_first_project(int $user_id): bool
    {
        $realizace = get_posts([
            'post_type' => 'realizace',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'suppress_filters' => true
        ]);

        $faktury = get_posts([
            'post_type' => 'faktura',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'suppress_filters' => true
        ]);


        return !empty($realizace) || !empty($faktury);
    }

    /**
     * Get project upload progress percentage
     */
    private function get_project_progress_percentage(int $user_id): int
    {
        // Check if user has any draft projects (realizace or faktury)
        $realizace_drafts = get_posts([
            'post_type' => 'realizace',
            'author' => $user_id,
            'post_status' => 'draft',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        $faktury_drafts = get_posts([
            'post_type' => 'faktura',
            'author' => $user_id,
            'post_status' => 'draft',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        // If user has drafts, they're 50% there
        if (!empty($realizace_drafts) || !empty($faktury_drafts)) {
            return 50;
        }

        // Otherwise, no progress yet
        return 0;
    }

    /**
     * Get cached template path to avoid repeated file system checks
     */
    private function get_template_path(): string
    {
        static $template_path = null;

        if ($template_path === null) {
            // Check child theme first, then parent theme
            $template_path = get_stylesheet_directory() . '/templates/shortcodes/user-progress-guide.php';

            if (!file_exists($template_path)) {
                $template_path = get_template_directory() . '/templates/shortcodes/user-progress-guide.php';
            }
        }

        return $template_path;
    }

    /**
     * Render progress guide component using template with caching
     */
    private function render_progress_guide(array $data): string
    {
        $template_path = $this->get_template_path();

        // If template doesn't exist, return error message
        if (!file_exists($template_path)) {
            return '<div class="user-progress-guide-error">Template not found: ' . esc_html($template_path) . '</div>';
        }

        // Template system working correctly

        // Start output buffering
        ob_start();

        // Include template with data available in template scope
        include $template_path;

        return ob_get_clean();
    }

    /**
     * Override wrapper classes for progress guide
     */
    protected function get_wrapper_classes(array $attributes): string
    {
        $classes = [
            'mycred-shortcode',
            'mycred-' . str_replace('_', '-', $this->get_tag()),
            'user-progress-guide-wrapper'
        ];

        if (!empty($attributes['class'])) {
            $classes[] = sanitize_html_class($attributes['class']);
        }

        return implode(' ', $classes);
    }
}
