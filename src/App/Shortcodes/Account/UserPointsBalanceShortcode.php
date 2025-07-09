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
        // Get centralized balance summary
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
            'next_product' => $next_product_data
        ];
    }

    /**
     * Get next unaffordable product information
     * 
     * Uses ProductService to find the next product in progression
     * and calculates display data for the progress component.
     */
    private function get_next_product_info(int $user_id, float $available_points): ?array
    {
        // Use ProductService to get the next unaffordable product
        $next_product = $this->product_service->get_next_unaffordable_product($user_id);

        if (!$next_product) {
            // User can afford all products
            return null;
        }

        $price = (float) $next_product->get_price();
        $points_needed = (int) ($price - $available_points);
        $progress_percentage = $price > 0 ? min(100, ($available_points / $price) * 100) : 0;

        return [
            'product' => $next_product,
            'price' => (int) $price,
            'points_needed' => $points_needed,
            'progress_percentage' => round($progress_percentage, 1)
        ];
    }

    /**
     * Render login message for non-logged-in users
     */
    private function render_login_message(): string
    {
        ob_start(); ?>
        <div class="user-points-balance-login bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
            <p class="text-blue-700">
                Pro zobrazení vašeho zůstatku bodů se musíte 
                <a href="<?= esc_url(wp_login_url(get_permalink())) ?>" class="text-blue-800 underline hover:text-blue-900">přihlásit</a>.
            </p>
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
        <div class="<?= esc_attr($wrapper_classes) ?> user-points-balance bg-white rounded-lg shadow p-6">
            <div class="balance-header mb-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Váš zůstatek bodů</h3>
            </div>

            <div class="balance-info space-y-4">
                <!-- Current Balance Display -->
                <div class="balance-display">
                    <div class="total-balance flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <span class="balance-label text-sm font-medium text-gray-600">Celkový zůstatek:</span>
                        <span class="balance-value text-2xl font-bold text-gray-900">
                            <?= number_format($balance_data['total_balance']) ?> <span class="text-sm font-normal text-gray-600">bodů</span>
                        </span>
                    </div>
                    
                    <?php if ($balance_data['cart_reserved'] > 0): ?>
                        <div class="available-vs-reserved grid grid-cols-2 gap-4 mt-3">
                            <div class="available-points text-center p-3 bg-green-50 rounded">
                                <div class="text-sm text-green-600 font-medium">Dostupné</div>
                                <div class="text-lg font-bold text-green-800"><?= number_format($balance_data['available_points']) ?></div>
                            </div>
                            <div class="reserved-points text-center p-3 bg-yellow-50 rounded">
                                <div class="text-sm text-yellow-600 font-medium">V košíku</div>
                                <div class="text-lg font-bold text-yellow-800"><?= number_format($balance_data['cart_reserved']) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="available-points text-center p-3 bg-green-50 rounded mt-3">
                            <div class="text-sm text-green-600 font-medium">Dostupné k nákupu</div>
                            <div class="text-lg font-bold text-green-800"><?= number_format($balance_data['available_points']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Next Product Progress -->
                <?php if ($balance_data['next_product']): ?>
                    <div class="next-product-progress border-t pt-4">
                        <div class="progress-header mb-3">
                            <h4 class="text-sm font-medium text-gray-700 mb-1">Pokrok k další odměně</h4>
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span><?= esc_html($balance_data['next_product']['product']->get_name()) ?></span>
                                <span class="font-medium"><?= number_format($balance_data['next_product']['price']) ?> bodů</span>
                            </div>
                        </div>
                        
                        <div class="progress-bar-container mb-2">
                            <div class="progress-bar-background bg-gray-200 h-3 rounded-full relative overflow-hidden">
                                <div class="progress-bar-fill bg-red-600 h-full rounded-full transition-all duration-300" 
                                     style="width: <?= $balance_data['next_product']['progress_percentage'] ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-details flex items-center justify-between text-sm">
                            <span class="text-gray-600">
                                <?= $balance_data['next_product']['progress_percentage'] ?>% dokončeno
                            </span>
                            <span class="font-medium text-red-600">
                                Potřebujete ještě <?= number_format($balance_data['next_product']['points_needed']) ?> bodů
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-next-product border-t pt-4">
                        <div class="all-products-affordable bg-green-50 rounded-lg p-4 text-center">
                            <div class="celebration-icon text-2xl mb-2">🎉</div>
                            <h4 class="text-sm font-medium text-green-800 mb-1">Gratulujeme!</h4>
                            <p class="text-sm text-green-700">
                                Máte dostatek bodů na všechny dostupné odměny!
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php return ob_get_clean();
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