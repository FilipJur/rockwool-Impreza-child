<?php

declare(strict_types=1);

namespace MistrFachman\Base;


/**
 * Base Admin Asset Manager - Abstract Foundation for Asset Management
 *
 * Provides common patterns for managing CSS and JavaScript assets
 * in admin interfaces across all domains.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class AdminAssetManagerBase {

    /**
     * Get the domain-specific script handle prefix
     */
    abstract protected function getScriptHandlePrefix(): string;

    /**
     * Get the domain-specific localization object name
     */
    abstract protected function getLocalizationObjectName(): string;

    /**
     * Get domain-specific admin screen IDs where assets should load
     */
    abstract protected function getAdminScreenIds(): array;

    /**
     * Get the post type slug for this domain (English, for internal logic)
     * Used for consistent naming conventions
     */
    abstract protected function getPostType(): string;

    /**
     * Get the WordPress post type slug (Czech, for database/WP operations)
     * Used for CSS classes and WordPress integration
     */
    abstract protected function getWordPressPostType(): string;

    /**
     * Get domain-specific localized data for scripts
     */
    abstract protected function getDomainLocalizationData(): array;

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
     * Handle domain-specific asset loading (e.g., status dropdowns)
     * Override in child classes to implement domain-specific logic
     */
    protected function handle_domain_specific_assets(): void {
        // Default implementation - child classes override for specific behavior
    }

    /**
     * Enqueue admin styles
     */
    protected function enqueue_admin_styles(): void {
        // Use main theme CSS which includes admin styles
        wp_enqueue_style(
            'mistr-theme-css',
            get_stylesheet_uri(),
            ['admin-bar'],
            wp_get_theme()->get('Version')
        );
    }

    /**
     * Enqueue admin scripts
     */
    protected function enqueue_admin_scripts(): void {
        $script_handle = $this->getScriptHandlePrefix() . '-admin-js';
        
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
                $this->getLocalizationObjectName(),
                $this->getBaseLocalizationData()
            );
        }
    }

    /**
     * Get the active script handle to localize to
     */
    protected function getActiveScriptHandle(): ?string {
        // Check for the main theme admin script first (enqueued in functions.php)
        $handles = ['theme-admin-js', 'mistr-admin-js', $this->getScriptHandlePrefix() . '-admin-js'];
        
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
        $admin_screens = $this->getAdminScreenIds();
        
        
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
        $domain_data = $this->getDomainLocalizationData();
        
        return array_merge([
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonces' => $this->generateNonces(),
            'messages' => $this->getDefaultMessages()
        ], $domain_data);
    }

    /**
     * Generate domain-specific nonces using direct string concatenation
     */
    protected function generateNonces(): array {
        $post_type = $this->getPostType();
        
        return [
            'quick_action' => wp_create_nonce("mistr_fachman_{$post_type}_quick_action"),
            'bulk_approve' => wp_create_nonce("mistr_fachman_bulk_approve_{$post_type}")
        ];
    }

    /**
     * Get default localized messages
     * Override in child classes to customize messages
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
     * Enqueue status dropdown script with configuration
     * Generic method for any domain's status dropdown
     */
    public function enqueue_status_dropdown_script(array $config): void {
        global $post;


        if (!$post || $post->post_type !== $config['postType']) {
            return;
        }


        // Ensure admin.js is loaded (contains StatusDropdownManager)
        $this->enqueue_admin_scripts();

        $script_handle = $this->getActiveScriptHandle() ?? 'theme-admin-js';

        // Localize status dropdown configuration
        wp_localize_script(
            $script_handle,
            'mistrFachmanStatusDropdown',
            $config
        );
        
    }
}