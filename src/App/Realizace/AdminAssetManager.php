<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Admin Asset Manager - Styles & Scripts
 *
 * Manages CSS and JavaScript assets for realizace admin interface.
 * Provides centralized asset management with proper enqueueing and localization.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminAssetManager {

    /**
     * Enqueue admin assets for realizace management
     */
    public function enqueue_admin_assets(): void {
        // Load on user edit screens and users list page
        if (!$this->is_realizace_admin_screen()) {
            return;
        }

        $this->enqueue_admin_styles();
        $this->enqueue_admin_scripts();
        $this->localize_admin_scripts();
    }

    /**
     * Enqueue admin styles
     */
    private function enqueue_admin_styles(): void {
        // Use main theme CSS which includes admin styles
        wp_enqueue_style(
            'mistr-theme-css',
            get_stylesheet_uri(),
            ['admin-bar'],
            wp_get_theme()->get('Version')
        );
    }

    /**
     * Enqueue admin scripts (only if not already enqueued by Users)
     */
    private function enqueue_admin_scripts(): void {
        // Check if theme-admin-js is already enqueued by Users domain
        if (wp_script_is('theme-admin-js', 'enqueued')) {
            // Script already loaded by Users - just localize our data
            return;
        }
        
        // Use built admin.js which includes realizace management
        $main_admin_js = get_stylesheet_directory() . '/build/js/admin.js';
        $asset_file = get_stylesheet_directory() . '/build/js/admin.asset.php';
        
        if (file_exists($main_admin_js) && file_exists($asset_file)) {
            $asset_data = include $asset_file;
            
            wp_enqueue_script(
                'mistr-admin-js',
                get_stylesheet_directory_uri() . '/build/js/admin.js',
                $asset_data['dependencies'] ?? [],
                $asset_data['version'] ?? filemtime($main_admin_js),
                true
            );
        }
    }

    /**
     * Localize admin scripts with AJAX data
     */
    private function localize_admin_scripts(): void {
        // Localize to whichever script handle exists
        $script_handle = wp_script_is('theme-admin-js', 'enqueued') ? 'theme-admin-js' : 'mistr-admin-js';
        
        wp_localize_script(
            $script_handle,
            'mistrRealizaceAdmin',
            $this->get_localized_data()
        );
    }

    /**
     * Check if current screen is user edit page
     */
    private function is_realizace_admin_screen(): bool {
        $screen = get_current_screen();
        return $screen && in_array($screen->id, ['user-edit', 'profile', 'users']);
    }

    /**
     * Get localized script data for AJAX requests
     */
    public function get_localized_data(): array {
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonces' => [
                'quick_action' => wp_create_nonce('mistr_fachman_realizace_action'),
                'bulk_approve' => wp_create_nonce('mistr_fachman_bulk_approve_realizace')
            ],
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
                'confirm_bulk_approve' => __('Opravdu chcete hromadně schválit všechny čekající realizace tohoto uživatele?', 'mistr-fachman'),
                'processing' => __('Zpracovává se...', 'mistr-fachman'),
                'error_generic' => __('Došlo k chybě při zpracování požadavku.', 'mistr-fachman'),
                'approve_text' => __('Schválit', 'mistr-fachman'),
                'reject_text' => __('Odmítnout', 'mistr-fachman'),
                'bulk_approve_text' => __('Hromadně schválit', 'mistr-fachman')
            ]
        ];
    }
}