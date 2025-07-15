<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes\Account;

use MistrFachman\Shortcodes\ShortcodeBase;
use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;
use MistrFachman\Services\DomainConfigurationService;
use MistrFachman\Services\ProjectStatusService;

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

    private ProjectStatusService $project_status_service;

    public function __construct(
        Manager $ecommerce_manager,
        ProductService $product_service,
        UserService $user_service,
        ProjectStatusService $project_status_service
    ) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
        $this->project_status_service = $project_status_service;
    }

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
     * Get simple workflow state for CSS-driven rendering
     */
    private function get_workflow_state(int $user_id): array
    {
        $user_status = $this->user_service->get_user_registration_status($user_id);
        // Get project data only for realizations (not invoices)
        $project_data = $this->get_realization_project_data($user_id);

        // Determine current step (what user should focus on)
        $current_step = $this->get_current_step($user_status, $project_data);

        return [
            'current_step' => $current_step,
            'user_status' => $user_status,
            'registration' => [
                'state' => $user_status === 'full_member' ? 'completed' : 'pending',
                'points' => $user_status === 'full_member' ? DomainConfigurationService::getUserWorkflowReward('registration_completed') : 0,
                'text' => $this->get_registration_text($user_status)
            ],
            'project' => [
                'state' => $this->get_project_state($project_data, $user_status),
                'points' => DomainConfigurationService::getUserWorkflowReward('first_project_uploaded'),
                'text' => $this->get_project_text($project_data, $user_status)
            ],
            'rewards' => [
                'state' => $project_data['has_published'] ? 'available' : 'locked',
                'text' => $project_data['has_published'] ? 'Vyberte si první odměny' : 'Vyberte si první odměny za odvedenou práci'
            ]
        ];
    }

    /**
     * Determine which step user should focus on
     */
    private function get_current_step(string $user_status, array $project_data): string
    {
        if ($user_status !== 'full_member') {
            return 'registration';
        }

        if (!$project_data['has_uploaded']) {
            return 'project';
        }

        if ($project_data['has_published']) {
            return 'rewards';
        }

        if ($project_data['has_uploaded'] && !$project_data['has_published']) {
            return 'rewards'; // Pending project - focus on upcoming rewards
        }

        return 'project';
    }

    /**
     * Get registration status text
     */
    private function get_registration_text(string $user_status): string
    {
        return match($user_status) {
            'needs_form' => 'Dokončete registrační formulář',
            'awaiting_review' => 'Čeká na schválení',
            'full_member' => number_format(DomainConfigurationService::getUserWorkflowReward('registration_completed'), 0, ',', ' ') . ' bodů',
            default => 'V procesu'
        };
    }

    /**
     * Get project state
     */
    private function get_project_state(array $project_data, string $user_status): string
    {
        if ($user_status !== 'full_member') {
            return 'locked';
        }

        if ($project_data['has_published']) {
            return 'completed';
        }

        if ($project_data['has_uploaded']) {
            return $project_data['has_rejected'] ? 'rejected' : 'pending';
        }

        return 'awaiting';
    }

    /**
     * Get project text based on state
     */
    private function get_project_text(array $project_data, string $user_status): string
    {
        if ($user_status !== 'full_member') {
            return '';
        }

        $state = $this->get_project_state($project_data, $user_status);

        return match($state) {
            'completed' => number_format(DomainConfigurationService::getUserWorkflowReward('first_project_uploaded'), 0, ',', ' ') . ' bodů',
            'pending' => number_format(DomainConfigurationService::getUserWorkflowReward('first_project_uploaded'), 0, ',', ' ') . ' bodů',
            'rejected' => 'Odmítnuto',
            'awaiting' => 'Nahrajte první projekt',
            default => ''
        };
    }

    /**
     * Get user's progress data for CSS-driven rendering
     */
    private function get_progress_data(int $user_id): array
    {
        // Get simplified workflow state
        $workflow = $this->get_workflow_state($user_id);

        return [
            'workflow' => $workflow,
            'registration' => [
                'title' => 'Dokončená registrace',
                'state' => $workflow['registration']['state'],
                'text' => $workflow['registration']['text'],
                'is_current' => $workflow['current_step'] === 'registration'
            ],
            'project' => [
                'title' => $this->get_project_title($workflow['project']['state']),
                'state' => $workflow['project']['state'],
                'text' => $workflow['project']['text'],
                'is_current' => $workflow['current_step'] === 'project'
            ],
            'rewards' => [
                'title' => $workflow['rewards']['text'],
                'state' => $workflow['rewards']['state'],
                'text' => '',
                'is_current' => $workflow['current_step'] === 'rewards'
            ]
        ];
    }

    /**
     * Get project title based on state
     */
    private function get_project_title(string $state): string
    {
        return match($state) {
            'completed' => 'První nahraná realizace',
            'pending' => 'Realizace čeká na schválení',
            'rejected' => 'Realizace byla odmítnuta',
            'awaiting' => 'Nahrajte svou první realizaci',
            default => 'Nahrajte svou první realizaci'
        };
    }

    /**
     * Get project data specifically for realizations only (not invoices)
     */
    private function get_realization_project_data(int $user_id): array
    {
        // Only check 'realizace' post type for progress guide
        $realization_post_types = ['realizace'];
        
        $has_uploaded = !empty($this->project_status_service->getProjectsForUser(
            $user_id, 
            ['pending', 'publish', 'rejected'], 
            $realization_post_types
        ));
        
        $has_published = !empty($this->project_status_service->getProjectsForUser(
            $user_id, 
            ['publish'], 
            $realization_post_types
        ));
        
        $has_rejected = !empty($this->project_status_service->getProjectsForUser(
            $user_id, 
            ['rejected'], 
            $realization_post_types
        ));

        return [
            'has_uploaded' => $has_uploaded,
            'has_published' => $has_published,
            'has_rejected' => $has_rejected,
            'progress_percentage' => $this->calculate_realization_progress($user_id, $has_published, $has_uploaded, $has_rejected)
        ];
    }

    /**
     * Calculate progress percentage for realizations only
     */
    private function calculate_realization_progress(int $user_id, bool $has_published, bool $has_uploaded, bool $has_rejected): int
    {
        if ($has_published) {
            return 100;
        }

        if ($has_uploaded && !$has_rejected) {
            return 75; // Pending
        }

        if ($has_rejected) {
            return 50; // Needs revision
        }

        // Check for drafts
        $drafts = $this->project_status_service->getProjectsForUser($user_id, ['draft'], ['realizace']);
        if (!empty($drafts)) {
            return 25;
        }

        return 0;
    }

    /**
     * @deprecated Use ProjectStatusService::getCachedUserProjects() instead
     * Kept for backward compatibility
     */
    private function get_cached_user_projects(int $user_id): array
    {
        return $this->project_status_service->getCachedUserProjects($user_id);
    }

    /**
     * @deprecated Use ProjectStatusService::hasUploadedFirstProject() instead
     * Kept for backward compatibility
     */
    private function has_uploaded_first_project(int $user_id): bool
    {
        return $this->project_status_service->hasUploadedFirstProject($user_id);
    }

    /**
     * @deprecated Use ProjectStatusService::hasPublishedFirstProject() instead
     * Kept for backward compatibility
     */
    private function has_published_first_project(int $user_id): bool
    {
        return $this->project_status_service->hasPublishedFirstProject($user_id);
    }

    /**
     * @deprecated Use ProjectStatusService::getProjectProgressPercentage() instead
     * Kept for backward compatibility
     */
    private function get_project_progress_percentage(int $user_id): int
    {
        return $this->project_status_service->getProjectProgressPercentage($user_id);
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
