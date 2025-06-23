<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Admin Setup - Clean Service Coordinator
 *
 * Orchestrates specialized admin services for realizace management.
 * Follows enterprise architecture with clear separation of concerns.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminSetup {

    public function __construct(
        private StatusManager $status_manager,
        private AdminUIManager $ui_manager,
        private AdminAjaxHandler $ajax_handler,
        private AdminAssetManager $asset_manager
    ) {
        error_log('[REALIZACE:SETUP] AdminSetup coordinator initialized');
        
        // Smart status registration based on WordPress lifecycle
        if (did_action('init')) {
            error_log('[REALIZACE:SETUP] Init already done - registering status immediately');
            $this->status_manager->register_rejected_status();
        } else {
            error_log('[REALIZACE:SETUP] Registering init hook for status');
            add_action('init', [$this->status_manager, 'register_rejected_status'], 1);
        }
    }

    /**
     * Initialize all admin hooks by delegating to specialized services
     */
    public function init_hooks(): void {
        error_log('[REALIZACE:SETUP] Initializing hooks across all services');
        
        // Initialize specialized service hooks
        $this->status_manager->init_hooks();
        $this->ui_manager->init_hooks();
        $this->ajax_handler->init_hooks();
        
        // Asset management
        add_action('admin_enqueue_scripts', [$this->asset_manager, 'enqueue_admin_assets']);
        
        error_log('[REALIZACE:SETUP] All service hooks initialized');
    }

    /**
     * Get the status manager instance
     * 
     * @return StatusManager
     */
    public function get_status_manager(): StatusManager {
        return $this->status_manager;
    }

    /**
     * Get the UI manager instance
     * 
     * @return AdminUIManager
     */
    public function get_ui_manager(): AdminUIManager {
        return $this->ui_manager;
    }

    /**
     * Get the AJAX handler instance
     * 
     * @return AdminAjaxHandler
     */
    public function get_ajax_handler(): AdminAjaxHandler {
        return $this->ajax_handler;
    }

    /**
     * Validate rejected status implementation (delegated to StatusManager)
     * 
     * @param int|null $post_id Optional post ID to check specific post
     * @return array Diagnostic information
     */
    public function validate_rejected_status(?int $post_id = null): array {
        return $this->status_manager->validate_status_implementation($post_id);
    }
}