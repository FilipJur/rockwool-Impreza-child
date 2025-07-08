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
        
        // Initialize admin components with centralized configuration
        $card_renderer = new FakturyCardRenderer();
        $asset_config = \MistrFachman\Services\DomainConfigurationService::getAssetConfig('invoice');
        $asset_manager = new \MistrFachman\Services\AdminAssetManager($asset_config);
        
        $status_config = \MistrFachman\Services\DomainConfigurationService::getStatusConfig('invoice');
        $status_manager = new \MistrFachman\Services\StatusManager(
            $status_config['post_type'],
            $status_config['rejected_status'],
            $status_config['rejected_label']
        );
        
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
     * Uses centralized points calculation service
     */
    protected function getCalculatedPoints(int $post_id = 0): int {
        return \MistrFachman\Services\PointsCalculationService::calculatePoints('invoice', $post_id);
    }

    /**
     * Get the points field selector
     */
    protected function getPointsFieldSelector(): string {
        return \MistrFachman\Services\DomainConfigurationService::getFieldSelector('invoice', 'points');
    }

}