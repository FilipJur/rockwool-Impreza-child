<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes\Account;

use MistrFachman\Shortcodes\ShortcodeBase;
use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * User Points Balance Shortcode Component
 *
 * Displays user's current points balance and progress toward next affordable product.
 * Shows balance info and visual progress bar.
 *
 * Usage: [user_points_balance]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UserPointsBalanceShortcode extends ShortcodeBase
{
    protected array $default_attributes = [
        'show_available_points' => 'true',
        'show_next_product' => 'true',
        'class' => ''
    ];

    public function get_tag(): string
    {
        return 'user_points_balance';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_message();
        }

        $user_id = get_current_user_id();
        
        // Get balance data
        $balance_data = $this->get_balance_data($user_id, $attributes);
        
        // Prepare template data
        $template_data = [
            'balance_data' => $balance_data,
            'attributes' => $attributes,
            'wrapper_classes' => $this->get_wrapper_classes($attributes),
            'user_id' => $user_id
        ];

        return $this->render_balance_display($template_data);
    }

    /**
     * Get user's balance and next product data
     */
    private function get_balance_data(int $user_id, array $attributes): array
    {
        // Get centralized balance summary (now includes pending points)
        $balance_summary = $this->ecommerce_manager->get_balance_summary($user_id);

        // Get next affordable product info
        $next_product_data = null;
        if ($attributes['show_next_product'] === 'true') {
            $next_product_data = $this->get_next_product_info($user_id, $balance_summary['available']);
        }

        return [
            'total_balance' => (int) $balance_summary['total'],
            'available_points' => (float) $balance_summary['available'],
            'cart_reserved' => (int) $balance_summary['reserved'],
            'pending_points' => (int) $balance_summary['pending'], // New pending points data
            'next_product' => $next_product_data
        ];
    }

    /**
     * Get next unaffordable product information
     * 
     * Uses ProductService to find the next product in progression
     * and calculates display data for the progress component with three segments:
     * accepted points, pending points, and remaining capacity.
     */
    private function get_next_product_info(int $user_id, float $available_points): ?array
    {
        // Use ProductService centralized method to check if user can afford all products
        if ($this->product_service->can_user_afford_all_products($user_id)) {
            return null;
        }

        // Use ProductService to get the next unaffordable product
        $next_product = $this->product_service->get_next_unaffordable_product($user_id);

        if (!$next_product) {
            return null;
        }

        $price = (float) $next_product->get_price();
        $points_needed = (int) ($price - $available_points);
        
        // Get balance summary for detailed progress calculation
        $balance_summary = $this->ecommerce_manager->get_balance_summary($user_id);
        $accepted_points = (float) $balance_summary['available']; // Available = accepted points
        $pending_points = (float) $balance_summary['pending'];
        $total_user_points = $accepted_points + $pending_points;
        
        // Calculate progress percentages for three-segment bar
        $accepted_percentage = $price > 0 ? min(100, ($accepted_points / $price) * 100) : 0;
        $pending_end_percentage = $price > 0 ? min(100, ($total_user_points / $price) * 100) : 0;
        
        return [
            'product' => $next_product,
            'price' => (int) $price,
            'points_needed' => $points_needed,
            'accepted_percentage' => round($accepted_percentage, 1),
            'pending_end_percentage' => round($pending_end_percentage, 1),
            'has_pending' => $pending_points > 0
        ];
    }

    /**
     * Render login message for non-logged-in users
     */
    private function render_login_message(): string
    {
        ob_start(); ?>
        <div class="user-points-balance login-required">
            <div class="login-message">
                <p>
                    Pro zobrazení vašeho zůstatku bodů se musíte 
                    <a href="<?= esc_url(wp_login_url(get_permalink())) ?>">přihlásit</a>.
                </p>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * Render balance display component
     */
    private function render_balance_display(array $data): string
    {
        extract($data);
        
        ob_start(); ?>
        <div class="<?= esc_attr($wrapper_classes) ?> user-points-balance<?= !$balance_data['next_product'] ? ' success-state' : '' ?>">
            <!-- Success State Congratulations - First position when in success state -->
            <?php if (!$balance_data['next_product']): ?>
                <div class="success-congratulations">
                    <div class="success-icon-wrapper">
                        <svg class="success-icon" width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.6 21.748L5.7 18.548L2.1 17.748L2.45 14.048L0 11.248L2.45 8.44805L2.1 4.74805L5.7 3.94805L7.6 0.748047L11 2.19805L14.4 0.748047L16.3 3.94805L19.9 4.74805L19.55 8.44805L22 11.248L19.55 14.048L19.9 17.748L16.3 18.548L14.4 21.748L11 20.298L7.6 21.748ZM9.95 14.798L15.6 9.14805L14.2 7.69805L9.95 11.948L7.8 9.84805L6.4 11.248L9.95 14.798Z" fill="#DB0626"/>
                        </svg>
                    </div>
                    <span class="success-text">Gratulujeme! Máte dostatek bodů na jakoukoliv odměnu.</span>
                </div>
            <?php endif; ?>
            
            <!-- Balance Header Section -->
            <div class="balance-header">
                <!-- Main Balance Display -->
                <div class="main-balance">
                    <div class="balance-label">Aktuální počet<br>bodů</div>
                    <div class="balance-value"><?= number_format($balance_data['total_balance']) ?>&nbsp;b.</div>
                </div>
                
                <!-- Pending Points Section - Directly below balance -->
                <?php $pending_points = $balance_data['pending_points'] ?? 0; ?>
                <?php if ($pending_points > 0): ?>
                    <div class="pending-section">
                        <span class="pending-label">Čeká na schválení</span>
                        <div class="pending-separator"></div>
                        <div class="pending-pill">
                            <span class="pending-value"><?= number_format($pending_points) ?>&nbsp;b.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Progress Section -->
            <?php if ($balance_data['next_product']): ?>
                <div class="progress-section">
                    <div class="progress-bar-container">
                        <!-- Base layer: remaining capacity (gray background) -->
                        <div class="progress-bar-background"></div>
                        
                        <!-- Middle layer: pending points (pink) -->
                        <?php if ($balance_data['next_product']['has_pending']): ?>
                            <div class="progress-bar-pending" style="width: <?= $balance_data['next_product']['pending_end_percentage'] ?>%"></div>
                        <?php endif; ?>
                        
                        <!-- Top layer: accepted points (red) -->
                        <div class="progress-bar-accepted" style="width: <?= $balance_data['next_product']['accepted_percentage'] ?>%"></div>
                    </div>
                    <div class="progress-text">
                        Zbývá <?= number_format($balance_data['next_product']['points_needed']) ?>b. do odemčení dalších odměn
                    </div>
                </div>
            <?php else: ?>
                <!-- Success State: No progress bar needed -->
                <div class="progress-section success-spacer">
                    <!-- Progress section hidden in success state, just maintains spacing -->
                </div>
            <?php endif; ?>
            
            <!-- Navigation Section -->
            <div class="navigation">
                <a href="<?= esc_url($this->get_points_page_url()) ?>" class="nav-link">
                    Moje body
                    <svg class="nav-arrow" width="12" height="9" viewBox="0 0 12 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0.000651209 3.83069L9.38419 3.83069L6.75919 1.20569L7.58402 0.380859L11.6172 4.41403L7.58402 8.44719L6.75919 7.62236L9.38419 4.99736L0.000651158 4.99736L0.000651209 3.83069Z" fill="#DB0626"/>
                    </svg>
                </a>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * Get URL for the user's points history page
     * 
     * @return string Points page URL
     */
    private function get_points_page_url(): string 
    {
        // Try to find a dedicated points page, fallback to account page
        $account_page_id = get_option('woocommerce_myaccount_page_id');
        if ($account_page_id) {
            return get_permalink($account_page_id);
        }
        
        // Fallback to current page if no account page found
        return get_permalink();
    }

    /**
     * Override wrapper classes for balance display
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
     * Override sanitize_attributes for balance-specific attributes
     */
    protected function sanitize_attributes(array $attributes): array
    {
        $sanitized = parent::sanitize_attributes($attributes);
        
        $sanitized['show_available_points'] = in_array($sanitized['show_available_points'], ['true', 'false'], true) ? $sanitized['show_available_points'] : 'true';
        $sanitized['show_next_product'] = in_array($sanitized['show_next_product'], ['true', 'false'], true) ? $sanitized['show_next_product'] : 'true';
        
        return $sanitized;
    }
}