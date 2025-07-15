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
        
        // Get balance summary for pending points
        $balance_summary = $this->ecommerce_manager->get_balance_summary($user_id);
        $pending_points = (int) $balance_summary['pending'];
        
        // Get next target (user above in leaderboard)
        $next_target = $this->get_next_target_user($user_position, $current_year, $user_points, $pending_points);
        
        return [
            'current_position' => $user_position,
            'current_points' => $user_points,
            'pending_points' => $pending_points,
            'next_target' => $next_target,
            'year' => $current_year
        ];
    }

    /**
     * Get the next target user to overtake
     */
    private function get_next_target_user(int $current_position, string $year, int $user_points, int $pending_points): ?array
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
        $target_points = (int) $target_user['points'];
        
        // Calculate points needed and progress percentages like UserPointsBalance
        $points_needed = max(1, $target_points - $user_points + 1);
        $total_user_points = $user_points + $pending_points;
        
        // Calculate progress percentages for three-segment bar
        $accepted_percentage = $target_points > 0 ? min(100, ($user_points / $target_points) * 100) : 0;
        $pending_end_percentage = $target_points > 0 ? min(100, ($total_user_points / $target_points) * 100) : 0;
        
        // Format display name: First name + First letter of surname
        $formatted_name = $this->format_display_name($target_user['display_name']);
        
        return [
            'position' => $target_position,
            'user_id' => $target_user['user_id'],
            'display_name' => $formatted_name,
            'company' => $target_user['company'],
            'points' => $target_points,
            'points_needed' => $points_needed,
            'accepted_percentage' => round($accepted_percentage, 1),
            'pending_end_percentage' => round($pending_end_percentage, 1),
            'has_pending' => $pending_points > 0
        ];
    }

    /**
     * Format display name to "First name + First letter of surname"
     */
    private function format_display_name(string $full_name): string
    {
        $name_parts = explode(' ', trim($full_name));
        
        if (count($name_parts) < 2) {
            return $full_name; // Return as-is if no surname
        }
        
        $first_name = $name_parts[0];
        $surname_initial = substr($name_parts[count($name_parts) - 1], 0, 1);
        
        return $first_name . ' ' . $surname_initial . '.';
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
        // Load template with progress data
        ob_start();
        $progress_data = $data['progress_data'];
        include get_stylesheet_directory() . '/templates/zebricek/progress.php';
        return ob_get_clean();
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