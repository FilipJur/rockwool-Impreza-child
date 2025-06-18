<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Admin Asset Manager - Asset Management for Admin
 *
 * Handles enqueuing and management of assets for the admin interface.
 * JavaScript functionality is now handled by ES6 modules via build process.
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
     * Enqueue admin assets
     *
     * @param string $hook_suffix Current admin page
     */
    public function enqueue_admin_assets(string $hook_suffix): void {
        // Debug logging
        error_log('AdminAssetManager::enqueue_admin_assets called with hook: ' . $hook_suffix);
        
        // Load on users list page AND user edit pages
        if (!in_array($hook_suffix, ['users.php', 'user-edit.php', 'profile.php'], true)) {
            error_log('AdminAssetManager: Skipping asset loading - not on users page. Hook: ' . $hook_suffix);
            return;
        }

        error_log('AdminAssetManager: Loading admin assets for: ' . $hook_suffix);

        // Check if scripts are registered
        global $wp_scripts, $wp_styles;
        
        $main_js_registered = wp_script_is('theme-main-js', 'registered');
        $main_css_registered = wp_style_is('impreza-child-style', 'registered');
        
        error_log('AdminAssetManager: theme-main-js registered: ' . ($main_js_registered ? 'YES' : 'NO'));
        error_log('AdminAssetManager: impreza-child-style registered: ' . ($main_css_registered ? 'YES' : 'NO'));

        if ($main_js_registered) {
            wp_enqueue_script('theme-main-js');
            error_log('AdminAssetManager: Enqueued theme-main-js');
        } else {
            error_log('AdminAssetManager: ERROR - theme-main-js not registered!');
        }

        if ($main_css_registered) {
            wp_enqueue_style('impreza-child-style');
            error_log('AdminAssetManager: Enqueued impreza-child-style');
        } else {
            error_log('AdminAssetManager: ERROR - impreza-child-style not registered!');
        }

        // Localize script for AJAX
        if ($main_js_registered) {
            $nonce = wp_create_nonce('mistr_fachman_business_data');
            wp_localize_script('theme-main-js', 'mistrFachmanAjax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => $nonce,
                'nonce_action' => 'mistr_fachman_business_data'
            ]);
            error_log('AdminAssetManager: Localized script with AJAX data');
            error_log('AdminAssetManager: Generated nonce: ' . $nonce . ' for action: mistr_fachman_business_data');
        }

        // Debug what's actually enqueued
        $enqueued_scripts = wp_scripts()->queue ?? [];
        $enqueued_styles = wp_styles()->queue ?? [];
        error_log('AdminAssetManager: Currently enqueued scripts: ' . implode(', ', $enqueued_scripts));
        error_log('AdminAssetManager: Currently enqueued styles: ' . implode(', ', $enqueued_styles));
    }
}