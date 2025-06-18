<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * User Status Header Shortcode Component
 *
 * Displays conditional content based on user registration status.
 * Shows appropriate actions and messages for each stage of the registration process.
 *
 * Usage: [user_status_header]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UserStatusHeaderShortcode extends ShortcodeBase
{
    protected array $default_attributes = [
        'show_for_logged_out' => 'false',  // Show content for logged out users
        'class' => ''                       // Additional CSS classes
    ];

    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
    }

    public function get_tag(): string
    {
        return 'user_status_header';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // Get the user's current registration status
        $status = $this->user_service->get_user_registration_status();
        
        // Skip rendering for logged out users unless explicitly requested
        if ($status === 'logged_out' && $attributes['show_for_logged_out'] !== 'true') {
            return '';
        }

        // Prepare URLs (should be moved to a settings service later)
        $finish_reg_url = home_url('/finish-registration');
        $login_url = wp_login_url(get_permalink());
        
        // Get wrapper classes
        $wrapper_classes = $this->get_wrapper_classes($attributes);
        $status_display = $this->user_service->get_status_display_name($status);

        // JSX-like template rendering
        ob_start();
        ?>
        <div class="<?= esc_attr($wrapper_classes) ?>" data-user-status="<?= esc_attr($status) ?>">
            <?php switch ($status):
                case 'needs_form': ?>
                    <div class="status-alert status-needs-form">
                        <div class="status-content">
                            <h3 class="status-title">Dokončete svou registraci</h3>
                            <p class="status-message">
                                Pro plný přístup k našim službám prosím vyplňte závěrečný registrační formulář.
                            </p>
                            <div class="status-actions">
                                <a href="<?= esc_url($finish_reg_url) ?>" class="button button-primary">
                                    Dokončit registraci
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php break;
                
                case 'awaiting_review': ?>
                    <div class="status-alert status-awaiting-review">
                        <div class="status-content">
                            <h3 class="status-title">Registrace odeslána</h3>
                            <p class="status-message">
                                Děkujeme za vyplnění registračního formuláře. Vaše registrace nyní čeká na schválení našeho týmu.
                                O výsledku vás budeme informovat e-mailem.
                            </p>
                            <div class="status-info">
                                <span class="status-badge">Čeká na schválení</span>
                            </div>
                        </div>
                    </div>
                    <?php break;
                    
                case 'full_member': ?>
                    <div class="status-alert status-full-member">
                        <div class="status-content">
                            <h3 class="status-title">Vítejte mezi našimi členy!</h3>
                            <p class="status-message">
                                Vaše registrace byla schválena. Máte nyní plný přístup ke všem funkcím včetně nakupování.
                            </p>
                            <?php
                            $user_balance = $this->ecommerce_manager->get_user_balance();
                            $available_points = $this->ecommerce_manager->get_available_points();
                            ?>
                            <div class="status-info">
                                <span class="balance-info">
                                    Váš zůstatek: <strong><?= number_format($user_balance) ?> bodů</strong>
                                    | Dostupné: <strong><?= number_format($available_points) ?> bodů</strong>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php break;
                    
                case 'logged_out': ?>
                    <div class="status-alert status-logged-out">
                        <div class="status-content">
                            <h3 class="status-title">Přihlaste se ke svému účtu</h3>
                            <p class="status-message">
                                Pro přístup k našim službám se prosím přihlaste nebo zaregistrujte.
                            </p>
                            <div class="status-actions">
                                <a href="<?= esc_url($login_url) ?>" class="button button-primary">
                                    Přihlásit se
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php break;
                    
                // Default case for other roles
                default: ?>
                    <div class="status-alert status-other">
                        <div class="status-content">
                            <p class="status-message">
                                Stav účtu: <strong><?= esc_html($status_display) ?></strong>
                            </p>
                        </div>
                    </div>
                    <?php break;
            endswitch; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get wrapper CSS classes for this shortcode
     */
    protected function get_wrapper_classes(array $attributes): string
    {
        $classes = [
            'user-status-header',
            'mycred-shortcode',
            'user-status-' . sanitize_html_class($this->user_service->get_user_registration_status())
        ];

        // Add purchase capability classes
        if ($this->user_service->can_user_purchase()) {
            $classes[] = 'can-purchase';
        } else {
            $classes[] = 'cannot-purchase';
        }

        if (!empty($attributes['class'])) {
            $classes[] = sanitize_html_class($attributes['class']);
        }

        return implode(' ', $classes);
    }
}