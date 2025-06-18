<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * User Debug Shortcode Component
 *
 * Enhanced debug shortcode to display comprehensive user status information for testing.
 * Features collapsible interface, security controls, and improved visual design.
 * Shows roles, meta, status, permissions, and MyCred balance information.
 *
 * Usage: 
 * - [user_debug] - Default: collapsed, visible to all users
 * - [user_debug collapsed="false"] - Start expanded
 * - [user_debug admin_only="true"] - Restrict to administrators only
 * - [user_debug collapsed="false" admin_only="true"] - Expanded, admin-only
 *
 * @package mistr-fachman
 * @since 1.0.0
 * @version 2.0.0 - Added toggle functionality and security controls
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
        // Parse attributes with defaults
        $attributes = shortcode_atts([
            'collapsed' => 'true',  // Start collapsed by default
            'admin_only' => 'false' // Option to restrict to admins only
        ], $attributes);

        // Security check - if admin_only is true, only show to admins
        if ($attributes['admin_only'] === 'true' && !current_user_can('manage_options')) {
            return '<div class="user-debug-info__restricted">🔒 Debug info restricted to administrators</div>';
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $is_collapsed = $attributes['collapsed'] === 'true';
        
        // Get all the key information
        $status = $this->user_service->get_user_registration_status();
        $can_purchase = $this->user_service->can_user_purchase();
        $can_view_products = $this->user_service->can_user_view_products();
        $status_display = $this->user_service->get_status_display_name($status);
        
        // Generate unique ID for this instance
        $debug_id = 'user-debug-' . wp_generate_uuid4();
        
        ob_start();
        ?>
        <div class="user-debug-info">
            <div class="user-debug-info__header" onclick="toggleUserDebug('<?= $debug_id ?>')">
                <h3>🔍 User Debug Information</h3>
                <span class="toggle-icon <?= $is_collapsed ? 'collapsed' : '' ?>">▼</span>
            </div>
            
            <div id="<?= $debug_id ?>" class="user-debug-info__content <?= !$is_collapsed ? 'expanded' : '' ?>">
                <div class="user-debug-info__grid">
                    <div class="user-debug-info__section">
                        <h4>👤 Basic User Info</h4>
                        <ul>
                            <li><strong>User ID:</strong> <span class="status-indicator"><?= $user_id ?: 'Not logged in' ?></span></li>
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
                                    <li><span class="status-indicator <?= $role ?>"><?= esc_html($role) ?></span></li>
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
                                <li><strong>Meta Key:</strong> <code><?= esc_html($meta_key) ?></code></li>
                                <li><strong>Meta Value:</strong> <span class="status-indicator <?= $meta_value ?>"><?= $meta_value ?: 'Not set' ?></span></li>
                            </ul>
                        <?php else: ?>
                            <p>User not logged in</p>
                        <?php endif; ?>
                    </div>

                    <div class="user-debug-info__section">
                        <h4>🔐 User Status & Permissions</h4>
                        <ul>
                            <li><strong>Registration Status:</strong> <span class="status-indicator <?= $status ?>"><?= esc_html($status) ?></span></li>
                            <li><strong>Status Display:</strong> <?= esc_html($status_display) ?></li>
                            <li><strong>Can Purchase:</strong> <span class="status-indicator <?= $can_purchase ? 'can-purchase' : 'cannot-purchase' ?>"><?= $can_purchase ? '✅ Yes' : '❌ No' ?></span></li>
                            <li><strong>Can View Products:</strong> <span class="status-indicator <?= $can_view_products ? 'can-purchase' : 'cannot-purchase' ?>"><?= $can_view_products ? '✅ Yes' : '❌ No' ?></span></li>
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
                            <li><strong>Status Class:</strong> <code><?= esc_html($this->user_service->get_status_css_class($status)) ?></code></li>
                            <li><strong>Purchase Class:</strong> <code><?= $can_purchase ? 'can-purchase' : 'cannot-purchase' ?></code></li>
                        </ul>
                    </div>
                </div>

                <div class="user-debug-info__section">
                    <h4>⚙️ Role Manager Tests</h4>
                    <?php if ($user_id): ?>
                        <?php
                        $role_manager = new \MistrFachman\Users\RoleManager();
                        $is_pending = $role_manager->is_pending_user($user_id);
                        $is_full_member = $role_manager->is_full_member($user_id);
                        $user_status_from_manager = $role_manager->get_user_status($user_id);
                        ?>
                        <ul>
                            <li><strong>Is Pending User:</strong> <span class="status-indicator <?= $is_pending ? 'pending' : 'approved' ?>"><?= $is_pending ? '✅ Yes' : '❌ No' ?></span></li>
                            <li><strong>Is Full Member:</strong> <span class="status-indicator <?= $is_full_member ? 'approved' : 'pending' ?>"><?= $is_full_member ? '✅ Yes' : '❌ No' ?></span></li>
                            <li><strong>Status from Manager:</strong> <span class="status-indicator <?= $user_status_from_manager ?>"><?= $user_status_from_manager ?: 'Not set' ?></span></li>
                        </ul>
                    <?php else: ?>
                        <p>User not logged in</p>
                    <?php endif; ?>
                </div>

                <div class="user-debug-info__warning">
                    <small><strong>⚠️ DEBUG MODE:</strong> This shortcode shows sensitive user data and should only be used for testing!</small>
                </div>
            </div>
        </div>

        <script>
        function toggleUserDebug(debugId) {
            const content = document.getElementById(debugId);
            const icon = content.previousElementSibling.querySelector('.toggle-icon');
            
            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                icon.classList.add('collapsed');
            } else {
                content.classList.add('expanded');
                icon.classList.remove('collapsed');
            }
        }
        </script>
        <?php
        return ob_get_clean();
    }
}