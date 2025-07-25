<?php

declare(strict_types=1);

namespace MistrFachman\Users;

use MistrFachman\Services\UserService;
use MistrFachman\Services\UserDetectionService;

/**
 * Users Domain Manager - Domain Orchestrator
 *
 * Main orchestrator for the Users domain, handling initialization
 * and coordination between user-related components.
 *
 * IMPORTANT: This class orchestrates components but does NOT implement
 * admin UI functionality directly. All admin UI logic is delegated to
 * the AdminInterface class to maintain clean separation of concerns.
 *
 * Role: Domain coordination and component lifecycle management
 * Admin UI: Delegated to AdminInterface (single source of truth)
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
    public BusinessDataManager $business_manager;
    public BusinessDataValidator $business_validator;
    public ThemeIntegration $theme_integration;
    public AccessControl $access_control;
    public AdminInterface $admin_interface;

    /**
     * Get singleton instance with dependency injection
     * For backward compatibility, parameters are optional if instance already exists
     */
    public static function get_instance(
        ?RoleManager $role_manager = null,
        ?UserDetectionService $user_detection_service = null,
        ?AresApiClient $ares_api_client = null,
        ?\MistrFachman\Services\UserStatusService $user_status_service = null
    ): self {
        if (self::$instance === null) {
            if ($role_manager === null || $user_detection_service === null || $ares_api_client === null || $user_status_service === null) {
                throw new \InvalidArgumentException('All dependencies must be provided on first call to Users Manager');
            }
            self::$instance = new self($role_manager, $user_detection_service, $ares_api_client, $user_status_service);
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     * Now accepts shared services via dependency injection
     */
    private function __construct(
        RoleManager $role_manager,
        UserDetectionService $user_detection_service,
        AresApiClient $ares_api_client,
        private \MistrFachman\Services\UserStatusService $user_status_service
    ) {
        // Store injected dependencies
        $this->role_manager = $role_manager;
        $this->user_detection_service = $user_detection_service;
        $this->user_service = new UserService($this->role_manager, $this->user_status_service);
        
        // Initialize business data components
        $this->business_manager = new BusinessDataManager();
        
        // Use injected ARES client and create dependent services
        $this->business_validator = new BusinessDataValidator($this->business_manager, $ares_api_client);
        $business_data_processor = new BusinessDataProcessor($ares_api_client, $this->business_manager);
        $profile_sync = new UserProfileSync();
        $eligibility_checker = new RegistrationEligibility($this->role_manager, $this->business_manager);
        $registration_validator = new RegistrationValidator(
            $this->business_validator,
            $eligibility_checker,
            $this->business_manager,
            $this->user_detection_service
        );
        
        $this->registration_hooks = new RegistrationHooks(
            $this->role_manager, 
            $this->user_detection_service,
            $this->business_validator,
            $this->business_manager,
            $profile_sync,
            $registration_validator,
            $eligibility_checker,
            $business_data_processor,
            $this->user_service
        );
        $this->theme_integration = new ThemeIntegration($this->user_service);
        $this->access_control = new AccessControl($this->user_service, $this->user_status_service);
        
        // Initialize admin interface components
        $style_manager = new AdminStyleManager();
        $card_renderer = new AdminCardRenderer($this->role_manager, $this->business_manager, $this->user_status_service);
        $asset_manager = new AdminAssetManager();
        $action_handler = new AdminActionHandler($this->role_manager, $this->user_service);
        $modal_renderer = new BusinessDataModalRenderer();
        $business_modal = new BusinessDataModal($this->registration_hooks, $modal_renderer);
        
        $this->admin_interface = new AdminInterface(
            $this->role_manager,
            $this->registration_hooks,
            $style_manager,
            $card_renderer,
            $asset_manager,
            $action_handler,
            $business_modal,
            $this->business_validator,
            $this->business_manager
        );

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