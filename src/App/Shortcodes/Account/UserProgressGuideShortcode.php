<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes\Account;

use MistrFachman\Shortcodes\ShortcodeBase;
use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

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
     * Get user's progress data
     */
    private function get_progress_data(int $user_id): array
    {
        $user_status = $this->user_service->get_user_registration_status($user_id);

        // Registration step progress based on status
        $registration_completed = ($user_status === 'full_member');
        $registration_progress = 0;
        if ($user_status === 'needs_form') {
            $registration_progress = 33; // Initial registration done, need form
        } elseif ($user_status === 'awaiting_review') {
            $registration_progress = 66; // Form submitted, awaiting approval
        } elseif ($user_status === 'full_member') {
            $registration_progress = 100; // Approved
        }

        // Check first project upload (only for full members)
        $first_project_uploaded = false;
        $project_progress = 0;
        if ($user_status === 'full_member') {
            $first_project_uploaded = $this->has_uploaded_first_project($user_id);
            $project_progress = $first_project_uploaded ? 100 : $this->get_project_progress_percentage($user_id);
        }

        return [
            'user_status' => $user_status,
            'registration' => [
                'completed' => $registration_completed,
                'progress_percentage' => $registration_progress,
                'points' => $registration_completed ? 1000 : 0
            ],
            'first_project' => [
                'completed' => $first_project_uploaded,
                'progress_percentage' => $project_progress,
                'points' => $first_project_uploaded ? 2500 : 0
            ]
        ];
    }

    /**
     * Check if user has uploaded first project
     */
    private function has_uploaded_first_project(int $user_id): bool
    {
        $projects = get_posts([
            'post_type' => 'realization',
            'author' => $user_id,
            'post_status' => ['publish', 'pending'],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        return !empty($projects);
    }

    /**
     * Get project upload progress percentage
     */
    private function get_project_progress_percentage(int $user_id): int
    {
        // Check if user has any draft projects
        $drafts = get_posts([
            'post_type' => 'realization',
            'author' => $user_id,
            'post_status' => 'draft',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        // If user has drafts, they're 50% there
        if (!empty($drafts)) {
            return 50;
        }

        // Otherwise, no progress yet
        return 0;
    }

    /**
     * Render progress guide component
     */
    private function render_progress_guide(array $data): string
    {
        extract($data);

        ob_start(); ?>
        <div class="<?= esc_attr($wrapper_classes) ?> user-progress-guide bg-white shadow-sm rounded-lg p-6 mb-8">
            <div class="progress-guide-header mb-6">
                <h2 class="text-2xl font-semibold text-gray-900 mb-2">A je to! Co teď?</h2>
            </div>

            <div class="progress-steps space-y-6">
                <!-- Registration Step -->
                <div class="progress-step registration-step">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="step-indicator <?= $progress_data['registration']['completed'] ? 'completed' : 'pending' ?> flex items-center justify-center w-8 h-8 rounded-full <?= $progress_data['registration']['completed'] ? 'bg-red-600 text-white' : 'bg-gray-300 text-gray-600' ?>">
                            <?php if ($progress_data['registration']['completed']): ?>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            <?php else: ?>
                                <span class="text-sm font-medium">1</span>
                            <?php endif; ?>
                        </div>

                        <div class="step-content flex-1">
                            <h3 class="step-title text-lg font-semibold text-gray-900">Dokončená registrace</h3>
                            <?php if ($progress_data['registration']['completed']): ?>
                                <div class="points-awarded inline-flex items-center gap-1 px-3 py-1 bg-red-50 text-red-800 text-sm font-medium rounded-full mt-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <?= number_format($progress_data['registration']['points']) ?> bodů
                                </div>
                            <?php else: ?>
                                <div class="status-pending inline-flex items-center gap-1 px-3 py-1 bg-yellow-50 text-yellow-800 text-sm font-medium rounded-full mt-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                    </svg>
                                    <?php
                                    echo match($progress_data['user_status']) {
                                        'needs_form' => 'Dokončete registrační formulář',
                                        'awaiting_review' => 'Čeká na schválení',
                                        default => 'V procesu'
                                    };
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="progress-bar-container ml-12">
                        <div class="progress-bar-background bg-gray-200 h-2 rounded-full relative overflow-hidden">
                            <div class="progress-bar-fill bg-red-600 h-full rounded-full transition-all duration-300"
                                 style="width: <?= $progress_data['registration']['progress_percentage'] ?>%"></div>
                        </div>
                        <?php if (!$progress_data['registration']['completed']): ?>
                            <div class="progress-text text-sm text-gray-600 mt-1">
                                <?= $progress_data['registration']['progress_percentage'] ?>% dokončeno
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- First Project Step -->
                <div class="progress-step project-step">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="step-indicator <?= $progress_data['first_project']['completed'] ? 'completed' : 'pending' ?> flex items-center justify-center w-8 h-8 rounded-full <?= $progress_data['first_project']['completed'] ? 'bg-red-600 text-white' : 'bg-gray-300 text-gray-600' ?>">
                            <?php if ($progress_data['first_project']['completed']): ?>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            <?php else: ?>
                                <span class="text-sm font-medium">2</span>
                            <?php endif; ?>
                        </div>

                        <div class="step-content flex-1">
                            <h3 class="step-title text-lg font-semibold text-gray-900">První nahraná realizace</h3>
                            <?php if ($progress_data['first_project']['completed']): ?>
                                <div class="points-awarded inline-flex items-center gap-1 px-3 py-1 bg-red-50 text-red-800 text-sm font-medium rounded-full mt-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <?= number_format($progress_data['first_project']['points']) ?> bodů
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="progress-bar-container ml-12">
                        <div class="progress-bar-background bg-gray-200 h-2 rounded-full relative overflow-hidden">
                            <div class="progress-bar-fill bg-<?= $progress_data['first_project']['completed'] ? 'red-600' : 'gray-400' ?> h-full rounded-full transition-all duration-300"
                                 style="width: <?= $progress_data['first_project']['progress_percentage'] ?>%"></div>
                        </div>
                        <?php if (!$progress_data['first_project']['completed'] && $progress_data['first_project']['progress_percentage'] > 0): ?>
                            <div class="progress-text text-sm text-gray-600 mt-1">
                                <?= $progress_data['first_project']['progress_percentage'] ?>% dokončeno
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * Override wrapper classes for progress guide
     */
    protected function get_wrapper_classes(array $attributes): string
    {
        $classes = [
            'mycred-shortcode',
            'mycred-' . str_replace('_', '-', $this->get_tag())
        ];

        if (!empty($attributes['class'])) {
            $classes[] = sanitize_html_class($attributes['class']);
        }

        return implode(' ', $classes);
    }
}
