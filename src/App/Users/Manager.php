<?php

declare(strict_types=1);

namespace MistrFachman\Users;

use MistrFachman\Services\UserService;
use MistrFachman\Services\UserDetectionService;

/**
 * Users Domain Manager
 *
 * Main orchestrator for the Users domain, handling initialization
 * and coordination between user-related components.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Manager {

    private static ?self $instance = null;

    public RoleManager $role_manager;
    public UserService $user_service;
    public UserDetectionService $user_detection_service;
    public RegistrationHooks $registration_hooks;
    public ThemeIntegration $theme_integration;
    public AccessControl $access_control;
    public AdminInterface $admin_interface;

    /**
     * Get singleton instance
     */
    public static function get_instance(): self {
        return self::$instance ??= new self();
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Initialize components in dependency order
        $this->role_manager = new RoleManager();
        $this->user_service = new UserService($this->role_manager);
        $this->user_detection_service = new UserDetectionService($this->role_manager);
        $this->registration_hooks = new RegistrationHooks($this->role_manager, $this->user_detection_service);
        $this->theme_integration = new ThemeIntegration($this->user_service);
        $this->access_control = new AccessControl($this->user_service);
        $this->admin_interface = new AdminInterface($this->role_manager);

        $this->setup_hooks();
    }

    /**
     * Setup WordPress hooks and initialize components
     */
    private function setup_hooks(): void {
        // Initialize all components
        $this->role_manager->init_hooks();
        $this->registration_hooks->init_hooks();
        $this->theme_integration->init_hooks();
        $this->access_control->init_hooks();
        $this->admin_interface->init_hooks();

        // Add filter for registration form ID configuration (legacy support)
        add_filter('mistr_fachman_registration_form_id', fn() => RegistrationConfig::getFormId());
        
        // Add admin notices handler
        add_action('admin_notices', [$this, 'display_admin_notices']);

        // Add admin hooks for user management
        if (is_admin()) {
            add_action('show_user_profile', [$this, 'add_user_status_fields']);
            add_action('edit_user_profile', [$this, 'add_user_status_fields']);
            add_action('personal_options_update', [$this, 'save_user_status_fields']);
            add_action('edit_user_profile_update', [$this, 'save_user_status_fields']);
            
            // Add secure admin action handler for user promotion
            add_action('admin_post_mistr_fachman_promote_user', [$this, 'handle_promote_user_action']);
        }

        mycred_debug('Users domain manager initialized', [
            'components' => [
                'role_manager' => true,
                'user_service' => true,
                'user_detection_service' => true,
                'registration_hooks' => true,
                'theme_integration' => true,
                'access_control' => true,
                'admin_interface' => true
            ]
        ], 'users', 'info');
    }

    /**
     * Configure the Contact Form 7 ID for final registration
     * 
     * @deprecated Use RegistrationConfig::getFormId() instead
     * @param string|int $form_id The Contact Form 7 form ID
     */
    public function set_registration_form_id(string|int $form_id): void {
        mycred_debug('Registration form ID configured via deprecated method', [
            'form_id' => $form_id,
            'configured_id' => RegistrationConfig::getFormId(),
            'note' => 'Use RegistrationConfig::getFormId() instead'
        ], 'users', 'warning');
    }

    /**
     * Get the configured registration form ID
     * 
     * @deprecated Use RegistrationConfig::getFormId() instead
     */
    public function get_registration_form_id(): int {
        return RegistrationConfig::getFormId();
    }

    /**
     * Display admin notices from transients
     */
    public function display_admin_notices(): void {
        $notice = get_transient('mistr_fachman_admin_notice');
        
        if ($notice && is_array($notice) && isset($notice['type'], $notice['message'])) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
            
            // Clear the transient after displaying
            delete_transient('mistr_fachman_admin_notice');
        }
    }

    /**
     * Add user status fields to admin user profile
     */
    public function add_user_status_fields(\WP_User $user): void {
        if (!current_user_can('edit_users')) {
            return;
        }

        $status = $this->user_service->get_user_registration_status($user->ID);
        $is_pending = $this->role_manager->is_pending_user($user->ID);
        $is_full_member = $this->role_manager->is_full_member($user->ID);
        $meta_status = $this->role_manager->get_user_status($user->ID);

        ?>
        <h2>Správa uživatele</h2>
        <table class="form-table">
            <tr>
                <th><label>Aktuální stav</label></th>
                <td>
                    <strong style="color: <?php echo $is_full_member ? 'green' : ($is_pending ? 'orange' : 'red'); ?>">
                        <?php echo esc_html($this->user_service->get_status_display_name($status)); ?>
                    </strong>
                    <p class="description">
                        Systémový status: <code><?php echo esc_html($status); ?></code><br>
                        <?php if ($meta_status): ?>
                            Meta status: <code><?php echo esc_html($meta_status); ?></code><br>
                        <?php endif; ?>
                        Role: <code><?php echo esc_html(implode(', ', $user->roles)); ?></code>
                    </p>
                </td>
            </tr>
            
            <?php if ($is_pending): ?>
            <tr>
                <th><label>Schválit uživatele</label></th>
                <td>
                    <a href="<?php echo esc_url(add_query_arg([
                        'action' => 'mistr_fachman_promote_user',
                        'user_id' => $user->ID,
                        '_wpnonce' => wp_create_nonce('promote_user_' . $user->ID)
                    ], admin_url('admin-post.php'))); ?>" 
                       class="button button-primary"
                       onclick="return confirm('Opravdu chcete povýšit tohoto uživatele na plného člena?')">
                        Povýšit na Plného člena
                    </a>
                    <p class="description">
                        Tímto krokem změníte roli uživatele na "Plný člen" a udělíte mu plný přístup k e-shopu.
                        <strong>Tato akce je nevratná.</strong>
                    </p>
                </td>
            </tr>
            <?php elseif ($is_full_member): ?>
            <tr>
                <th><label>Stav člena</label></th>
                <td>
                    <span style="color: green; font-weight: bold;">✓ Plný člen</span>
                    <p class="description">Uživatel má plný přístup k e-shopu a může nakupovat.</p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Handle secure admin action for promoting users
     */
    public function handle_promote_user_action(): void {
        // 1. Security Check: Verify nonce and user capabilities
        if (!isset($_GET['user_id']) || !isset($_GET['_wpnonce'])) {
            wp_die('Missing required parameters.');
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'promote_user_' . $_GET['user_id'])) {
            wp_die('Security check failed.');
        }
        
        if (!current_user_can('edit_users')) {
            wp_die('You do not have permission to perform this action.');
        }

        // 2. Sanitize input and perform the action
        $user_id_to_promote = absint($_GET['user_id']);
        $success = $this->registration_hooks->promote_to_full_member($user_id_to_promote);

        // 3. Set admin notice and redirect back
        $notice_type = $success ? 'success' : 'error';
        $notice_message = $success ? 'User successfully promoted to Full Member.' : 'Failed to promote user.';
        
        // Store notice in transient for display after redirect
        set_transient('mistr_fachman_admin_notice', [
            'type' => $notice_type,
            'message' => $notice_message
        ], 30);

        // Redirect back to the users list
        wp_redirect(admin_url('users.php'));
        exit;
    }

    /**
     * Save user status fields (placeholder for future functionality)
     */
    public function save_user_status_fields(int $user_id): void {
        if (!current_user_can('edit_users')) {
            return;
        }

        // Future: Handle manual status changes from admin
    }

    /**
     * Get user service instance for external use
     */
    public function get_user_service(): UserService {
        return $this->user_service;
    }

    /**
     * Get role manager instance for external use
     */
    public function get_role_manager(): RoleManager {
        return $this->role_manager;
    }

    /**
     * Get access control instance for external use
     */
    public function get_access_control(): AccessControl {
        return $this->access_control;
    }

    /**
     * Configure allowed pages for logged-out users
     *
     * @param array $page_slugs Array of page slugs to allow
     */
    public function set_allowed_pages_for_logged_out(array $page_slugs): void {
        foreach ($page_slugs as $slug) {
            $this->access_control->add_allowed_page_for_logged_out($slug);
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup(): void {
        throw new \Exception("Cannot unserialize singleton");
    }
}