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

        // Check if admin scripts are registered
        global $wp_scripts, $wp_styles;
        
        $admin_js_registered = wp_script_is('theme-admin-js', 'registered');
        $main_css_registered = wp_style_is('impreza-child-style', 'registered');
        
        error_log('AdminAssetManager: theme-admin-js registered: ' . ($admin_js_registered ? 'YES' : 'NO'));
        error_log('AdminAssetManager: impreza-child-style registered: ' . ($main_css_registered ? 'YES' : 'NO'));

        if ($admin_js_registered) {
            wp_enqueue_script('theme-admin-js');
            error_log('AdminAssetManager: Enqueued theme-admin-js');
        } else {
            error_log('AdminAssetManager: ERROR - theme-admin-js not registered!');
        }

        // Use consistent admin CSS handle and prevent duplicates
        $admin_css_handle = 'mistr-admin-css';
        if (!wp_style_is($admin_css_handle, 'enqueued') && !wp_style_is($admin_css_handle, 'done')) {
            // Enqueue with standard handle if not already loaded
            wp_enqueue_style(
                $admin_css_handle,
                get_stylesheet_uri(),
                ['admin-bar'],
                wp_get_theme()->get('Version')
            );
            error_log('AdminAssetManager: Enqueued mistr-admin-css');
        } else {
            error_log('AdminAssetManager: mistr-admin-css already loaded, skipping');
        }

        // Localize script for AJAX
        if ($admin_js_registered) {
            $business_data_nonce = wp_create_nonce('mistr_fachman_business_data');
            $ico_validation_nonce = wp_create_nonce('mistr_fachman_ico_validation_nonce');
            wp_localize_script('theme-admin-js', 'mistrFachmanAjax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'business_data_nonce' => $business_data_nonce,
                'ico_validation_nonce' => $ico_validation_nonce,
                'nonce' => $business_data_nonce, // Keep backward compatibility
                'nonce_action' => 'mistr_fachman_business_data'
            ]);
            error_log('AdminAssetManager: Localized script with AJAX data');
            error_log('AdminAssetManager: Generated business_data_nonce: ' . $business_data_nonce);
            error_log('AdminAssetManager: Generated ico_validation_nonce: ' . $ico_validation_nonce);
        }

        // Debug what's actually enqueued
        $enqueued_scripts = wp_scripts()->queue ?? [];
        $enqueued_styles = wp_styles()->queue ?? [];
        error_log('AdminAssetManager: Currently enqueued scripts: ' . implode(', ', $enqueued_scripts));
        error_log('AdminAssetManager: Currently enqueued styles: ' . implode(', ', $enqueued_styles));
    }
}