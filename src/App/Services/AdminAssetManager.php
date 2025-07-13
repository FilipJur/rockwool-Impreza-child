<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Unified Admin Asset Manager Service
 *
 * Handles admin asset management (CSS, JavaScript, and localization) for any domain.
 * This service consolidates all asset management logic into a single, configurable class.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

final class AdminAssetManager {

    private array $config;

    /**
     * Constructor accepts configuration array with domain-specific settings
     */
    public function __construct(array $config) {
        $this->config = array_merge([
            'script_handle_prefix' => '',
            'localization_object_name' => '',
            'admin_screen_ids' => [],
            'post_type' => '',
            'wordpress_post_type' => '',
            'domain_localization_data' => [],
            'field_service_class' => null
        ], $config);
    }

    /**
     * Enqueue admin assets with conditional logic
     */
    public function enqueue_admin_assets(): void {
        // Only enqueue assets on relevant admin screens
        if (!$this->is_admin_screen()) {
            return;
        }
        
        // Since admin.js is now globally enqueued in functions.php,
        // we just need to localize scripts when this method is called
        
        $this->enqueue_admin_styles();
        $this->localize_admin_scripts();
        
        // Handle domain-specific asset requirements
        $this->handle_domain_specific_assets();
    }

    /**
     * Handle domain-specific asset loading
     */
    protected function handle_domain_specific_assets(): void {
        // Domain-specific assets can be added here if needed
        // Status dropdown functionality has been replaced with dedicated reject button
    }

    /**
     * Enqueue admin styles with duplicate prevention
     */
    protected function enqueue_admin_styles(): void {
        // Standard handle for admin CSS across all domains
        $admin_css_handle = 'mistr-admin-css';
        
        // Check if CSS is already enqueued by another domain manager
        if (wp_style_is($admin_css_handle, 'enqueued') || wp_style_is($admin_css_handle, 'done')) {
            return; // Already loaded
        }
        
        // Enqueue admin CSS with consistent handle
        wp_enqueue_style(
            $admin_css_handle,
            get_stylesheet_uri(),
            ['admin-bar'],
            wp_get_theme()->get('Version')
        );
    }

    /**
     * Enqueue admin scripts
     */
    protected function enqueue_admin_scripts(): void {
        $script_handle = $this->config['script_handle_prefix'] . '-admin-js';
        
        // Check if script is already enqueued by another domain
        if (wp_script_is('theme-admin-js', 'enqueued') || wp_script_is('mistr-admin-js', 'enqueued')) {
            // Script already loaded - just localize our data
            return;
        }
        
        // Use built admin.js
        $admin_js_path = get_stylesheet_directory() . '/build/js/admin.js';
        $asset_file_path = get_stylesheet_directory() . '/build/js/admin.asset.php';
        
        if (file_exists($admin_js_path) && file_exists($asset_file_path)) {
            $asset_data = include $asset_file_path;
            
            wp_enqueue_script(
                $script_handle,
                get_stylesheet_directory_uri() . '/build/js/admin.js',
                $asset_data['dependencies'] ?? [],
                $asset_data['version'] ?? filemtime($admin_js_path),
                true
            );
        }
    }

    /**
     * Localize admin scripts with domain-specific data
     */
    protected function localize_admin_scripts(): void {
        // Determine which script handle to use
        $script_handle = $this->getActiveScriptHandle();
        
        if ($script_handle) {
            wp_localize_script(
                $script_handle,
                $this->config['localization_object_name'],
                $this->getBaseLocalizationData()
            );
        }
    }

    /**
     * Get the active script handle to localize to
     */
    protected function getActiveScriptHandle(): ?string {
        // Check for the main theme admin script first (enqueued in functions.php)
        $handles = ['theme-admin-js', 'mistr-admin-js', $this->config['script_handle_prefix'] . '-admin-js'];
        
        foreach ($handles as $handle) {
            if (wp_script_is($handle, 'enqueued')) {
                return $handle;
            }
        }
        
        return null;
    }

    /**
     * Check if current screen should load assets
     */
    protected function is_admin_screen(): bool {
        $screen = get_current_screen();
        $admin_screens = $this->config['admin_screen_ids'];
        
        if (!$screen) {
            return false;
        }
        
        // Check direct screen ID match
        if (in_array($screen->id, $admin_screens)) {
            return true;
        }
        
        // Check for post edit pages
        if ($screen->base === 'post' && isset($screen->post_type)) {
            if (in_array($screen->post_type, $admin_screens) || in_array('post', $admin_screens)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get base localization data (common patterns)
     */
    protected function getBaseLocalizationData(): array {
        return array_merge([
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonces' => $this->generateNonces(),
            'messages' => $this->getDefaultMessages()
        ], $this->config['domain_localization_data']);
    }

    /**
     * Generate domain-specific nonces using direct string concatenation
     */
    protected function generateNonces(): array {
        $post_type = $this->config['post_type'];
        
        return [
            'quick_action' => wp_create_nonce("mistr_fachman_{$post_type}_quick_action"),
            'bulk_approve' => wp_create_nonce("mistr_fachman_bulk_approve_{$post_type}")
        ];
    }

    /**
     * Get default localized messages
     */
    protected function getDefaultMessages(): array {
        return [
            'confirm_approve' => __('Opravdu chcete schválit tuto položku?', 'mistr-fachman'),
            'confirm_reject' => __('Opravdu chcete odmítnout tuto položku?', 'mistr-fachman'),
            'confirm_bulk_approve' => __('Opravdu chcete hromadně schválit všechny čekající položky tohoto uživatele?', 'mistr-fachman'),
            'processing' => __('Zpracovává se...', 'mistr-fachman'),
            'error_generic' => __('Došlo k chybě při zpracování požadavku.', 'mistr-fachman'),
            'approve_text' => __('Schválit', 'mistr-fachman'),
            'reject_text' => __('Odmítnout', 'mistr-fachman'),
            'bulk_approve_text' => __('Hromadně schválit', 'mistr-fachman')
        ];
    }


    /**
     * Get localized script data for AJAX requests (backward compatibility)
     */
    public function get_localized_data(): array {
        return $this->getBaseLocalizationData();
    }
}