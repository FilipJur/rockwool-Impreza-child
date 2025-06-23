<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

use MistrFachman\Services\UserDetectionService;
use MistrFachman\Users\RoleManager;

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

class Manager {

    private static ?self $instance = null;

    public RealizaceFormHandler $form_handler;

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
        // Initialize dependencies
        $role_manager = new RoleManager();
        $user_detection_service = new UserDetectionService($role_manager);
        
        // Initialize form handler with dependencies
        $this->form_handler = new RealizaceFormHandler($user_detection_service);
        
        // Initialize admin components
        $card_renderer = new AdminCardRenderer();
        $asset_manager = new AdminAssetManager();
        $admin_setup = new AdminSetup($card_renderer, $asset_manager);
        $admin_setup->init_hooks();
        
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
        
        // Add CSS for disabled cursor
        $styles = '<style>
            .wpcf7-form input[disabled],
            .wpcf7-form input[readonly],
            .wpcf7-form textarea[disabled],
            .wpcf7-form textarea[readonly],
            .wpcf7-form select[disabled],
            .wpcf7-form button[disabled],
            .wpcf7-form .wpcf7-submit[disabled],
            .wpcf7-form .mistr-realizace-disabled * {
                cursor: not-allowed !important;
                pointer-events: none !important;
                opacity: 0.6 !important;
            }
            .wpcf7-form .mistr-realizace-disabled {
                cursor: not-allowed !important;
                user-select: none !important;
            }
        </style>';
        
        // Add a prominent notice about pending approval
        $notice = '<div class="mistr-realizace-notice" style="
            background: #fff3cd;
            border: 1px solid #ffd60a;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            color: #856404;
            font-size: 14px;
            line-height: 1.5;
        ">
            <strong style="display: block; margin-bottom: 5px; font-size: 16px;">⚠️ Formulář je nedostupný</strong>
            Váš účet čeká na schválení administrátorem. Po schválení budete moci přidávat realizace.
        </div>';
        
        error_log('[REALIZACE:DEBUG] Adding pending approval notice to form');
        
        $form_elements = $styles . $notice . $form_elements;

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
}