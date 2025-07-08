<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes\Account;

use MistrFachman\Shortcodes\ShortcodeBase;
use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;
use MistrFachman\Services\ZebricekDataService;

/**
 * Zebricek Progress Shortcode Component
 *
 * Displays user's progress toward overtaking the next user in leaderboard.
 * Shows current position and points needed to advance.
 *
 * Usage: [zebricek_progress]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZebricekProgressShortcode extends ShortcodeBase
{
    protected array $default_attributes = [
        'current_year' => '',
        'show_position' => 'true',
        'class' => ''
    ];

    private ZebricekDataService $zebricek_service;

    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service, ZebricekDataService $zebricek_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
        $this->zebricek_service = $zebricek_service;
    }

    public function get_tag(): string
    {
        return 'zebricek_progress';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_message();
        }

        $user_id = get_current_user_id();
        
        // Check user access level
        $status = $this->user_service->get_user_registration_status($user_id);
        if ($status !== 'full_member' && $status !== 'other') {
            return $this->render_pending_message();
        }

        // Get progress data
        $progress_data = $this->get_progress_data($user_id, $attributes);
        
        // Prepare template data
        $template_data = [
            'progress_data' => $progress_data,
            'attributes' => $attributes,
            'wrapper_classes' => $this->get_wrapper_classes($attributes),
            'user_id' => $user_id
        ];

        return $this->render_progress_display($template_data);
    }

    /**
     * Get user's leaderboard progress data
     */
    private function get_progress_data(int $user_id, array $attributes): array
    {
        $current_year = !empty($attributes['current_year']) ? $attributes['current_year'] : date('Y');
        
        // Get user's current position and points
        $user_position = $this->zebricek_service->get_user_position($user_id, $current_year);
        $user_points = $this->zebricek_service->get_user_annual_points($user_id, $current_year);
        
        // Get next target (user above in leaderboard)
        $next_target = $this->get_next_target_user($user_position, $current_year);
        
        return [
            'current_position' => $user_position,
            'current_points' => $user_points,
            'next_target' => $next_target,
            'year' => $current_year
        ];
    }

    /**
     * Get the next target user to overtake
     */
    private function get_next_target_user(int $current_position, string $year): ?array
    {
        if ($current_position <= 1) {
            return null; // Already at the top
        }

        // Get the user at position above current user
        $target_position = $current_position - 1;
        
        // Get leaderboard data for that position
        $leaderboard_data = $this->zebricek_service->get_leaderboard_data(1, $target_position - 1, $year);
        
        if (empty($leaderboard_data)) {
            return null;
        }

        $target_user = $leaderboard_data[0];
        
        return [
            'position' => $target_position,
            'user_id' => $target_user['user_id'],
            'display_name' => $target_user['display_name'],
            'company' => $target_user['company'],
            'points' => $target_user['points']
        ];
    }

    /**
     * Render login message for non-logged-in users
     */
    private function render_login_message(): string
    {
        ob_start(); ?>
        <div class="zebricek-progress-login bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
            <p class="text-blue-700">
                Pro zobrazení vašeho postupu v žebříčku se musíte 
                <a href="<?= esc_url(wp_login_url(get_permalink())) ?>" class="text-blue-800 underline hover:text-blue-900">přihlásit</a>.
            </p>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * Render pending user message
     */
    private function render_pending_message(): string
    {
        ob_start(); ?>
        <div class="zebricek-progress-pending bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
            <p class="text-yellow-700">
                Váš účet čeká na schválení. Po schválení se zobrazí váš postup v žebříčku.
            </p>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * Render progress display component
     */
    private function render_progress_display(array $data): string
    {
        extract($data);
        
        ob_start(); ?>
        <div class="<?= esc_attr($wrapper_classes) ?> zebricek-progress bg-white p-4 rounded-lg shadow">
            <div class="progress-header mb-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Postup v žebříčku</h3>
                <div class="current-position-summary text-sm text-gray-600">
                    Aktuálně jste na <span class="font-medium text-gray-900"><?= $progress_data['current_position'] ?>. pozici</span>
                    s <span class="font-medium text-gray-900"><?= number_format($progress_data['current_points']) ?> body</span>
                </div>
            </div>

            <?php if ($progress_data['next_target']): ?>
                <div class="next-position-target border-t pt-4">
                    <div class="target-info mb-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="target-user">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= esc_html($progress_data['next_target']['display_name']) ?>
                                </div>
                                <div class="text-xs text-gray-600">
                                    <?= esc_html($progress_data['next_target']['company']) ?>
                                </div>
                            </div>
                            <div class="target-position text-right">
                                <div class="text-sm font-medium text-red-600">
                                    <?= $progress_data['next_target']['position'] ?>. pozice
                                </div>
                                <div class="text-xs text-gray-600">
                                    <?= number_format($progress_data['next_target']['points']) ?> bodů
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php 
                    $points_needed = max(1, $progress_data['next_target']['points'] - $progress_data['current_points'] + 1);
                    $progress_percentage = $progress_data['current_points'] > 0 ? 
                        min(100, ($progress_data['current_points'] / $progress_data['next_target']['points']) * 100) : 0;
                    ?>
                    
                    <div class="points-needed mb-3">
                        <div class="progress-bar-container mb-2">
                            <div class="progress-bar-background bg-gray-200 h-3 rounded-full relative overflow-hidden">
                                <div class="progress-bar-fill bg-red-600 h-full rounded-full transition-all duration-300" 
                                     style="width: <?= round($progress_percentage, 1) ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-details flex items-center justify-between text-sm">
                            <span class="text-gray-600">
                                <?= round($progress_percentage, 1) ?>% k předběhnutí
                            </span>
                            <span class="font-medium text-red-600">
                                Potřebujete <?= number_format($points_needed) ?> bodů
                            </span>
                        </div>
                    </div>
                    
                    <div class="achievement-tips bg-gray-50 rounded p-3">
                        <div class="text-xs text-gray-600 mb-1">Tipy pro získání bodů:</div>
                        <div class="text-xs text-gray-700">
                            • Nahrajte realizaci (+2500 b.) • Přidejte fakturu (+body dle částky)
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="at-top border-t pt-4 text-center">
                    <div class="celebration-icon text-4xl mb-2">🏆</div>
                    <div class="text-lg font-semibold text-yellow-600 mb-1">
                        Gratulujeme!
                    </div>
                    <div class="text-sm text-gray-600">
                        Jste na vrcholu žebříčku pro rok <?= $progress_data['year'] ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * Override wrapper classes for progress display
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

    /**
     * Override sanitize_attributes for zebricek-specific attributes
     */
    protected function sanitize_attributes(array $attributes): array
    {
        $sanitized = parent::sanitize_attributes($attributes);
        
        $sanitized['show_position'] = in_array($sanitized['show_position'], ['true', 'false'], true) ? $sanitized['show_position'] : 'true';
        $sanitized['current_year'] = !empty($sanitized['current_year']) ? sanitize_text_field($sanitized['current_year']) : date('Y');
        
        return $sanitized;
    }
}