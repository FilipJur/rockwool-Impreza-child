<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes\Account;

use MistrFachman\Shortcodes\ShortcodeBase;
use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;
use MistrFachman\Services\ZebricekDataService;

/**
 * Account Dashboard Shortcode Component
 *
 * Main dashboard container that renders all account components in a 3-row layout.
 * Integrates progress guide, points balance, product grid, and leaderboard components.
 *
 * Usage: [account_dashboard]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AccountDashboardShortcode extends ShortcodeBase
{
    protected array $default_attributes = [
        'show_progress_guide' => 'auto', // auto, true, false
        'products_limit' => '12',
        'products_filter' => 'affordable',
        'class' => ''
    ];

    private ZebricekDataService $zebricek_service;

    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service, ZebricekDataService $zebricek_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
        $this->zebricek_service = $zebricek_service;
    }

    public function get_tag(): string
    {
        return 'account_dashboard';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_message();
        }

        $user_id = get_current_user_id();

        // Get dashboard configuration
        $config = $this->get_dashboard_config($user_id, $attributes);

        // Prepare template data
        $template_data = [
            'config' => $config,
            'attributes' => $attributes,
            'wrapper_classes' => $this->get_wrapper_classes($attributes),
            'user_id' => $user_id
        ];

        return $this->render_dashboard_layout($template_data);
    }

    /**
     * Get dashboard configuration based on user status
     */
    private function get_dashboard_config(int $user_id, array $attributes): array
    {
        $has_made_purchase = $this->ecommerce_manager->has_user_made_purchase($user_id);
        $user_status = $this->user_service->get_user_registration_status($user_id);

        // Determine if progress guide should show
        $show_progress_guide = false;
        if ($attributes['show_progress_guide'] === 'true') {
            $show_progress_guide = true;
        } elseif ($attributes['show_progress_guide'] === 'auto') {
            $show_progress_guide = !$has_made_purchase;
        }

        return [
            'show_progress_guide' => $show_progress_guide,
            'show_leaderboard' => ($user_status === 'full_member' || $user_status === 'other'),
            'has_made_purchase' => $has_made_purchase,
            'user_status' => $user_status
        ];
    }

    /**
     * Render login message for non-logged-in users
     */
    private function render_login_message(): string
    {
        ob_start(); ?>
        <div class="account-dashboard-login bg-blue-50 border border-blue-200 rounded-lg p-8 text-center">
            <div class="login-icon text-4xl mb-4">👤</div>
            <h2 class="text-xl font-semibold text-blue-900 mb-2">Přístup k účtu</h2>
            <p class="text-blue-700 mb-4">
                Pro zobrazení vašeho účtu a postupu v programu se musíte přihlásit.
            </p>
            <a href="<?= esc_url(wp_login_url(get_permalink())) ?>"
               class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors">
                Přihlásit se
            </a>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * Render dashboard layout with all components
     */
    private function render_dashboard_layout(array $data): string
    {
        extract($data);

        ob_start(); ?>
        <div class="<?= esc_attr($wrapper_classes) ?> account-dashboard">
            <div class="dashboard-container space-y-8">

                <?php if ($config['show_progress_guide']): ?>
                    <!-- Row 1: Progress Guide -->
                    <div class="dashboard-row progress-row">
                        <div class="dashboard-section full-width">
                            <?= do_shortcode('[user_progress_guide]') ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Row 2: Points Balance & Available Products -->
                <div class="dashboard-row balance-products-row">
                    <div class="dashboard-grid grid grid-cols-2 gap-6">
                        <!-- Left: Points Balance -->
                        <div class="dashboard-section balance-section">
                            <?= do_shortcode('[user_points_balance]') ?>
                        </div>

                        <!-- Right: Available Products -->
                        <div class="dashboard-section products-section">
                            <?= do_shortcode('[mycred_product_grid balance_filter="dynamic" limit="' . esc_attr($attributes['products_limit']) . '"]') ?>
                        </div>
                    </div>
                </div>

                <?php if ($config['show_leaderboard']): ?>
                    <!-- Row 3: Leaderboard Information -->
                    <div class="dashboard-row leaderboard-row">
                        <div class="dashboard-grid grid grid-cols-3 gap-6 w-full">
                            <!-- Left: Competition Announcement -->
                            <div class="dashboard-section announcement-section">
                                <?= do_shortcode('[zebricek_announcement]') ?>
                            </div>

                            <!-- Center: Current Position -->
                            <div class="dashboard-section position-section">
                                <?= do_shortcode('[zebricek_position]') ?>
                            </div>

                            <!-- Right: Progress to Next Position -->
                            <div class="dashboard-section progress-section">
                                <?= do_shortcode('[zebricek_progress]') ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Pending User Message -->
                    <div class="dashboard-row pending-row">
                        <div class="dashboard-section full-width">
                            <div class="pending-leaderboard bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                                <div class="pending-icon text-3xl mb-3">⏳</div>
                                <h3 class="text-lg font-semibold text-yellow-800 mb-2">
                                    Čeká na schválení
                                </h3>
                                <p class="text-yellow-700">
                                    Váš účet čeká na schválení. Po schválení se zobrazí informace o žebříčku a vaší pozici.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <?php return ob_get_clean();
    }

    /**
     * Override wrapper classes for dashboard
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
     * Override sanitize_attributes for dashboard-specific attributes
     */
    protected function sanitize_attributes(array $attributes): array
    {
        $sanitized = parent::sanitize_attributes($attributes);

        $sanitized['show_progress_guide'] = in_array($sanitized['show_progress_guide'], ['auto', 'true', 'false'], true) ? $sanitized['show_progress_guide'] : 'auto';
        $sanitized['products_limit'] = max(1, min(12, (int)$sanitized['products_limit']));
        $sanitized['products_filter'] = in_array($sanitized['products_filter'], ['all', 'affordable', 'unavailable'], true) ? $sanitized['products_filter'] : 'affordable';

        return $sanitized;
    }
}
