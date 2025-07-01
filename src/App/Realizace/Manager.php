<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

use MistrFachman\Base\PostTypeManagerBase;
use MistrFachman\Base\FormHandlerBase;
use MistrFachman\Services\UserDetectionService;

/**
 * Realizace Domain Manager - Domain Orchestrator
 *
 * Main orchestrator for the Realizace domain, handling initialization
 * and coordination between realizace-related components.
 *
 * Role: Domain coordination and component lifecycle management
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Manager extends PostTypeManagerBase {

    private static ?self $instance = null;

    public FormHandlerBase $form_handler;

    /**
     * Get singleton instance with dependency injection
     */
    public static function get_instance(?UserDetectionService $user_detection_service = null): self {
        if (self::$instance === null) {
            if ($user_detection_service === null) {
                throw new \InvalidArgumentException('UserDetectionService must be provided on first call');
            }
            self::$instance = new self($user_detection_service);
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     * Now accepts shared services via dependency injection
     */
    private function __construct(
        private UserDetectionService $user_detection_service
        // Future shared services will be added here (SMS, leaderboard, etc.)
    ) {
        // Initialize base class hooks
        $this->init_common_hooks();
        
        // Initialize taxonomies for relational select components
        TaxonomyManager::init();
        
        // Initialize form handler with injected dependencies
        $this->form_handler = new NewRealizaceFormHandler($this->user_detection_service, $this);
        
        // Initialize admin components with consolidated architecture
        $card_renderer = new RealizaceCardRenderer();
        $asset_manager = new \MistrFachman\Services\AdminAssetManager([
            'script_handle_prefix' => 'realization',
            'localization_object_name' => 'mistrRealizaceAdmin',
            'admin_screen_ids' => ['user-edit', 'profile', 'users', 'post', 'realizace'],
            'post_type' => 'realization',
            'wordpress_post_type' => 'realizace',
            'domain_localization_data' => [
                'field_names' => [
                    'rejection_reason' => RealizaceFieldService::getRejectionReasonFieldSelector(),
                    'points' => RealizaceFieldService::getPointsFieldSelector(),
                    'gallery' => RealizaceFieldService::getGalleryFieldSelector(),
                    'area' => RealizaceFieldService::getAreaFieldSelector(),
                    'construction_type' => RealizaceFieldService::getConstructionTypeFieldSelector(),
                    'materials' => RealizaceFieldService::getMaterialsFieldSelector()
                ],
                'default_values' => [
                    'points' => 2500
                ],
                'messages' => [
                    'confirm_approve' => __('Opravdu chcete schválit tuto realizaci?', 'mistr-fachman'),
                    'confirm_reject' => __('Opravdu chcete odmítnout tuto realizaci?', 'mistr-fachman'),
                    'confirm_bulk_approve' => __('Opravdu chcete hromadně schválit všechny čekající realizace tohoto uživatele?', 'mistr-fachman')
                ]
            ]
        ]);
        $status_manager = new \MistrFachman\Services\StatusManager('realization', 'rejected', 'Odmítnuto');
        
        // Create consolidated admin controller
        $admin_controller = new AdminController($status_manager, $card_renderer, $asset_manager);
        
        // Wire up card renderer to use admin controller's abstract methods
        $card_renderer->setAdminController($admin_controller);
        
        $admin_controller->init_hooks();
        
        // Initialize points handler for myCred integration
        $points_handler = new PointsHandler();
        $points_handler->init_hooks();
        
        // Hook form handler into WPCF7
        add_action('wpcf7_mail_sent', [$this->form_handler, 'handle_submission']);
        
        // Hook form field filtering for role-based access
        add_filter('wpcf7_form_elements', [$this, 'filter_form_fields_for_pending_users']);
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Filter form fields for pending approval users
     * 
     * Disables form fields for users with pending_approval role to prevent submissions
     * while still allowing them to see the form structure.
     * 
     * @param string $form_elements The form HTML content
     * @return string Modified form HTML with disabled fields for pending users
     */
    public function filter_form_fields_for_pending_users(string $form_elements): string {
        // Only process realizace forms
        if (!$this->is_realizace_form()) {
            return $form_elements;
        }

        // Check if current user has pending approval role
        if (!$this->is_user_pending_approval()) {
            return $form_elements;
        }

        error_log('[REALIZACE:DEBUG] Disabling form fields for pending approval user');

        // Add readonly/disabled attributes to form fields
        $form_elements = $this->disable_form_fields($form_elements);
        
        // Wrap form in disabled class container
        $form_elements = '<div class="mistr-realizace-disabled">' . $form_elements . '</div>';
        
        // Add a prominent notice about pending approval (styles are in SCSS)
        $notice = '<div class="mistr-realizace-notice">
            <strong class="notice-title">⚠️ Formulář je nedostupný</strong>
            Váš účet čeká na schválení administrátorem. Po schválení budete moci přidávat realizace.
        </div>';
        
        error_log('[REALIZACE:DEBUG] Adding pending approval notice to form');
        
        $form_elements = $notice . $form_elements;

        return $form_elements;
    }

    /**
     * Check if current form is the realizace form
     * @return bool True if this is the realizace form
     */
    private function is_realizace_form(): bool {
        // Get the current Contact Form 7 instance
        $contact_form = \WPCF7_ContactForm::get_current();
        
        if (!$contact_form) {
            return false;
        }
        
        return $contact_form->title() === 'Přidat realizaci';
    }

    /**
     * Check if current user has pending approval role
     * @return bool True if user has pending_approval role
     */
    private function is_user_pending_approval(): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('pending_approval', $user->roles, true);
    }

    /**
     * Disable form fields by adding readonly/disabled attributes
     * @param string $form_elements HTML form content
     * @return string Modified HTML with disabled fields
     */
    private function disable_form_fields(string $form_elements): string {
        // Disable text inputs, textareas, and selects
        $form_elements = preg_replace(
            '/(<input[^>]*type=["\'](?:text|email|tel|number)["\'][^>]*)(>)/i',
            '$1 readonly disabled$2',
            $form_elements
        );
        
        // Disable textareas
        $form_elements = preg_replace(
            '/(<textarea[^>]*)(>)/i',
            '$1 readonly disabled$2',
            $form_elements
        );
        
        // Disable file uploads
        $form_elements = preg_replace(
            '/(<input[^>]*type=["\']file["\'][^>]*)(>)/i',
            '$1 disabled$2',
            $form_elements
        );
        
        // Disable submit buttons
        $form_elements = preg_replace(
            '/(<input[^>]*type=["\']submit["\'][^>]*)(>)/i',
            '$1 disabled$2',
            $form_elements
        );
        
        // Disable selects
        $form_elements = preg_replace(
            '/(<select[^>]*)(>)/i',
            '$1 disabled$2',
            $form_elements
        );

        return $form_elements;
    }

    /**
     * Get the post type slug for this domain
     */
    protected function getPostType(): string {
        return 'realization';
    }

    /**
     * Get the calculated points value for realizace (fixed 2500)
     */
    public function getCalculatedPoints(int $post_id = 0): int {
        // Fixed value for Realizace domain
        unset($post_id); // Suppress unused parameter warning
        return 2500;
    }

    /**
     * Get the ACF field selector that stores points
     * Delegates to centralized field service
     */
    public function getPointsFieldSelector(): string {
        return RealizaceFieldService::getPointsFieldSelector();
    }

    /**
     * Get the display name for this domain
     */
    protected function getDomainDisplayName(): string {
        return 'Realizace';
    }
}