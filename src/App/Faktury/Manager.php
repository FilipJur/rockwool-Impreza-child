<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\PostTypeManagerBase;
use MistrFachman\Base\FormHandlerBase;
use MistrFachman\Services\UserDetectionService;

/**
 * Faktury Domain Manager - Domain Orchestrator
 *
 * Main orchestrator for the Faktury domain, handling initialization
 * and coordination between faktury-related components.
 * Mirrors Realizace Manager pattern exactly.
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
        
        // Initialize form handler with injected dependencies
        $this->form_handler = new NewFakturyFormHandler($this->user_detection_service, $this);
        
        // Initialize admin components with consolidated architecture
        $card_renderer = new FakturyCardRenderer();
        $asset_manager = new \MistrFachman\Services\AdminAssetManager([
            'script_handle_prefix' => 'invoice',
            'localization_object_name' => 'mistrFakturyAdmin',
            'admin_screen_ids' => ['user-edit', 'profile', 'users', 'post', 'faktura'],
            'post_type' => 'invoice',
            'wordpress_post_type' => 'faktura',
            'domain_localization_data' => [
                'field_names' => [
                    'rejection_reason' => FakturaFieldService::getRejectionReasonFieldSelector(),
                    'points' => FakturaFieldService::getPointsFieldSelector(),
                    'invoice_number' => FakturaFieldService::getInvoiceNumberFieldSelector(),
                    'value' => FakturaFieldService::getValueFieldSelector(),
                    'file' => FakturaFieldService::getFileFieldSelector(),
                    'invoice_date' => FakturaFieldService::getInvoiceDateFieldSelector()
                ],
                'default_values' => [
                    'points' => 100 // Different default for invoices
                ],
                'messages' => [
                    'confirm_approve' => __('Opravdu chcete schválit tuto fakturu?', 'mistr-fachman'),
                    'confirm_reject' => __('Opravdu chcete odmítnout tuto fakturu?', 'mistr-fachman'),
                    'confirm_bulk_approve' => __('Opravdu chcete hromadně schválit všechny čekající faktury tohoto uživatele?', 'mistr-fachman')
                ]
            ]
        ]);
        $status_manager = new \MistrFachman\Services\StatusManager('invoice', 'rejected', 'Odmítnuto');
        
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
    }

    /**
     * Get the post type slug for this domain
     */
    protected function getPostType(): string {
        return 'invoice';
    }

    /**
     * Get the display name for this domain
     */
    protected function getDomainDisplayName(): string {
        return 'Faktura';
    }

    /**
     * Get the calculated points for this domain
     * Delegates to PointsHandler for consistency
     */
    protected function getCalculatedPoints(int $post_id = 0): int {
        $points_handler = new PointsHandler();
        return $points_handler->getCalculatedPoints($post_id);
    }

    /**
     * Get the points field selector
     */
    protected function getPointsFieldSelector(): string {
        return FakturaFieldService::getPointsFieldSelector();
    }

}