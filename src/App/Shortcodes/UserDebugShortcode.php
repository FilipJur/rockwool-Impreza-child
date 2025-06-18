<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * User Debug Shortcode Component
 *
 * Simple debug shortcode to display all user status information for testing.
 * Shows roles, meta, status, and permissions.
 *
 * Usage: [user_debug]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UserDebugShortcode extends ShortcodeBase
{
    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
    }

    public function get_tag(): string
    {
        return 'user_debug';
    }

    protected function render(array $attributes, ?string $content = null): string
    {

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        // Get all the key information
        $status = $this->user_service->get_user_registration_status();
        $can_purchase = $this->user_service->can_user_purchase();
        $can_view_products = $this->user_service->can_user_view_products();
        $status_display = $this->user_service->get_status_display_name($status);
        
        ob_start();
        ?>
        <div class="user-debug-info" style="background: #f5f5f5; padding: 20px; border: 1px solid #ddd; font-family: monospace; margin: 20px 0;">
            <h3 style="margin-top: 0;">🔍 User Debug Information</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>👤 Basic User Info</h4>
                    <ul>
                        <li><strong>User ID:</strong> <?= $user_id ?: 'Not logged in' ?></li>
                        <?php if ($user): ?>
                            <li><strong>Username:</strong> <?= esc_html($user->user_login) ?></li>
                            <li><strong>Email:</strong> <?= esc_html($user->user_email) ?></li>
                            <li><strong>Display Name:</strong> <?= esc_html($user->display_name) ?></li>
                        <?php endif; ?>
                    </ul>

                    <h4>🎭 User Roles</h4>
                    <?php if ($user && $user->roles): ?>
                        <ul>
                            <?php foreach ($user->roles as $role): ?>
                                <li><?= esc_html($role) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No roles assigned</p>
                    <?php endif; ?>

                    <h4>📊 Registration Meta</h4>
                    <?php if ($user_id): ?>
                        <?php
                        $meta_key = \MistrFachman\Users\RoleManager::REG_STATUS_META_KEY;
                        $meta_value = get_user_meta($user_id, $meta_key, true);
                        ?>
                        <ul>
                            <li><strong>Meta Key:</strong> <?= esc_html($meta_key) ?></li>
                            <li><strong>Meta Value:</strong> <?= $meta_value ?: 'Not set' ?></li>
                        </ul>
                    <?php else: ?>
                        <p>User not logged in</p>
                    <?php endif; ?>
                </div>

                <div>
                    <h4>🔐 User Status & Permissions</h4>
                    <ul>
                        <li><strong>Registration Status:</strong> <?= esc_html($status) ?></li>
                        <li><strong>Status Display:</strong> <?= esc_html($status_display) ?></li>
                        <li><strong>Can Purchase:</strong> <?= $can_purchase ? '✅ Yes' : '❌ No' ?></li>
                        <li><strong>Can View Products:</strong> <?= $can_view_products ? '✅ Yes' : '❌ No' ?></li>
                    </ul>

                    <h4>💰 MyCred Info</h4>
                    <?php if (function_exists('mycred') && $user_id): ?>
                        <?php
                        $user_balance = $this->ecommerce_manager->get_user_balance();
                        $available_points = $this->ecommerce_manager->get_available_points();
                        ?>
                        <ul>
                            <li><strong>User Balance:</strong> <?= number_format($user_balance) ?> points</li>
                            <li><strong>Available Points:</strong> <?= number_format($available_points) ?> points</li>
                        </ul>
                    <?php else: ?>
                        <p>MyCred not active or user not logged in</p>
                    <?php endif; ?>

                    <h4>🏷️ CSS Classes</h4>
                    <ul>
                        <li><strong>Status Class:</strong> <?= esc_html($this->user_service->get_status_css_class($status)) ?></li>
                        <li><strong>Purchase Class:</strong> <?= $can_purchase ? 'can-purchase' : 'cannot-purchase' ?></li>
                    </ul>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <h4>⚙️ Role Manager Tests</h4>
                <?php if ($user_id): ?>
                    <?php
                    $role_manager = new \MistrFachman\Users\RoleManager();
                    $is_pending = $role_manager->is_pending_user($user_id);
                    $is_full_member = $role_manager->is_full_member($user_id);
                    $user_status_from_manager = $role_manager->get_user_status($user_id);
                    ?>
                    <ul>
                        <li><strong>Is Pending User:</strong> <?= $is_pending ? '✅ Yes' : '❌ No' ?></li>
                        <li><strong>Is Full Member:</strong> <?= $is_full_member ? '✅ Yes' : '❌ No' ?></li>
                        <li><strong>Status from Manager:</strong> <?= $user_status_from_manager ?: 'Not set' ?></li>
                    </ul>
                <?php else: ?>
                    <p>User not logged in</p>
                <?php endif; ?>
            </div>

            <div style="margin-top: 20px; padding: 10px; background: #fff; border-left: 4px solid #ff6b6b;">
                <small><strong>⚠️ LOCALHOST DEBUG:</strong> This shortcode shows sensitive user data and should only be used for testing!</small>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}