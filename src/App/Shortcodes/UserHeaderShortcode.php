<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;
use MistrFachman\Services\UserStatusService;

/**
 * User Header Shortcode Component
 *
 * Dynamic header element that displays different content based on user authentication status.
 * Handles all user states from anonymous to fully registered members.
 *
 * Usage: [user_header]
 *
 * States handled:
 * - Anonymous: "Přihlášení" with user icon
 * - OTP registered (needs_form): "Dokončete registraci" button
 * - Form submitted (awaiting_review): "Váš účet čeká na schválení" text
 * - Fully registered: User icon + "Přidat realizace" + "Přidat fakturu" buttons
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UserHeaderShortcode extends ShortcodeBase
{
    public function __construct(
        Manager $ecommerce_manager, 
        ProductService $product_service, 
        UserService $user_service,
        private UserStatusService $user_status_service
    ) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
    }

    public function get_tag(): string
    {
        return 'user_header';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // Parse attributes with defaults
        $attributes = shortcode_atts([
            'show_icons' => 'true',
            'login_url' => '/prihlaseni',
            'registration_url' => '/registrace',
            'account_url' => '/muj-ucet',
            'add_realization_url' => '/pridat-realizaci',
            'add_invoice_url' => '/pridat-fakturu'
        ], $attributes);

        $user_id = get_current_user_id();
        $status = $this->user_status_service->getCurrentUserStatus($user_id);
        $show_icons = $attributes['show_icons'] === 'true';

        // Get the user icon path
        $icon_url = get_stylesheet_directory_uri() . '/src/images/icons/user_alt.svg';

        ob_start();
        ?>
        <div class="user-header-widget">
            <?php if ($status === UserStatusService::STATUS_GUEST): ?>
                <!-- Anonymous user state -->
                <div class="user-header-widget__anonymous">
                    <?php if ($show_icons): ?>
                        <img src="<?= esc_url($icon_url) ?>" alt="User" class="user-header-widget__icon" />
                    <?php endif; ?>
                    <a href="<?= esc_url($attributes['login_url']) ?>" class="user-header-widget__login-link">
                        Přihlášení
                    </a>
                </div>

            <?php elseif ($status === UserStatusService::STATUS_NEEDS_FORM): ?>
                <!-- OTP registered, needs to complete form -->
                <div class="user-header-widget__needs-form">
                    <a href="<?= esc_url($attributes['registration_url']) ?>" class="w-btn us-btn-style_1">
                        Dokončete registraci
                    </a>
                </div>

            <?php elseif ($status === UserStatusService::STATUS_AWAITING_APPROVAL): ?>
                <!-- Form submitted, awaiting admin approval -->
                <div class="user-header-widget__awaiting-review">
                    <span class="user-header-widget__status-text">
                        Váš účet čeká na schválení
                    </span>
                </div>

            <?php elseif ($status === UserStatusService::STATUS_FULL_MEMBER || $status === UserStatusService::STATUS_OTHER): ?>
                <!-- Fully registered user (admin, full_member, customer, etc.) -->
                <div class="user-header-widget__full-member flex">
                    <div class="user-header-widget__user-account">
                        <?php if ($show_icons): ?>
                            <a href="<?= esc_url($attributes['account_url']) ?>" class="user-header-widget__account-link">
                                <img src="<?= esc_url($icon_url) ?>" alt="Můj účet" class="user-header-widget__icon" />
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="user-header-widget__actions">
                        <a href="<?= esc_url($attributes['add_realization_url']) ?>" class="w-btn us-btn-style_1">
                            Přidat realizace
                        </a>
                        <a href="<?= esc_url($attributes['add_invoice_url']) ?>" class="w-btn us-btn-style_2">
                            Přidat fakturu
                        </a>
                    </div>
                </div>

            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
