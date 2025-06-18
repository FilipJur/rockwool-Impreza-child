<?php

declare(strict_types=1);

namespace MistrFachman\Users;

use MistrFachman\Services\UserService;

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
    public RegistrationHooks $registration_hooks;
    public ThemeIntegration $theme_integration;
    public AccessControl $access_control;

    /**
     * Registration form ID configuration
     */
    private int $registration_form_id = 0;

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
        $this->registration_hooks = new RegistrationHooks($this->role_manager);
        $this->theme_integration = new ThemeIntegration($this->user_service);
        $this->access_control = new AccessControl($this->user_service);

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

        // Add filter for registration form ID configuration
        add_filter('mistr_fachman_registration_form_id', [$this, 'get_registration_form_id']);

        // Add admin hooks for user management
        if (is_admin()) {
            add_action('show_user_profile', [$this, 'add_user_status_fields']);
            add_action('edit_user_profile', [$this, 'add_user_status_fields']);
            add_action('personal_options_update', [$this, 'save_user_status_fields']);
            add_action('edit_user_profile_update', [$this, 'save_user_status_fields']);
        }

        mycred_debug('Users domain manager initialized', [
            'components' => [
                'role_manager' => true,
                'user_service' => true,
                'registration_hooks' => true,
                'theme_integration' => true,
                'access_control' => true
            ]
        ], 'users', 'info');
    }

    /**
     * Configure the Contact Form 7 ID for final registration
     *
     * @param int $form_id The Contact Form 7 form ID
     */
    public function set_registration_form_id(int $form_id): void {
        $this->registration_form_id = $form_id;
        
        mycred_debug('Registration form ID configured', [
            'form_id' => $form_id
        ], 'users', 'info');
    }

    /**
     * Get the configured registration form ID
     */
    public function get_registration_form_id(): int {
        return $this->registration_form_id;
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

        echo '<h3>Stav registrace</h3>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label>Aktuální stav</label></th>';
        echo '<td>';
        echo '<strong>' . esc_html($this->user_service->get_status_display_name($status)) . '</strong>';
        echo '<p class="description">Status: ' . esc_html($status) . '</p>';
        echo '</td>';
        echo '</tr>';

        if ($is_pending) {
            echo '<tr>';
            echo '<th><label>Akce</label></th>';
            echo '<td>';
            echo '<p><a href="' . esc_url(add_query_arg([
                'action' => 'promote_user',
                'user_id' => $user->ID,
                '_wpnonce' => wp_create_nonce('promote_user_' . $user->ID)
            ], admin_url('admin-post.php'))) . '" class="button button-primary">Povýšit na člena</a></p>';
            echo '<p class="description">Povýší uživatele na plného člena s možností nakupování.</p>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
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