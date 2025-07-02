<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

/**
 * CF7 Asset Manager
 *
 * Manages assets for Contact Form 7 tags without coupling them to individual components.
 * Provides centralized asset loading for CF7 relational selects.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CF7AssetManager {

    private static bool $assets_enqueued = false;

    /**
     * Enqueue Realizace CF7 components assets
     */
    public static function enqueue_realizace_components(): void {
        if (self::$assets_enqueued) {
            return;
        }

        // Enqueue components script first, then Alpine.js with dependency
        self::enqueue_cf7_components_script();
        self::enqueue_alpine_js();
        self::localize_script_data();

        self::$assets_enqueued = true;
    }

    /**
     * Enqueue Alpine.js if not already loaded
     */
    private static function enqueue_alpine_js(): void {
        if (!wp_script_is('alpine-js', 'enqueued')) {
            wp_enqueue_script(
                'alpine-js',
                'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
                ['cf7-alpine-components'],
                '3.0.0',
                true
            );
            wp_script_add_data('alpine-js', 'defer', true);
        }
    }

    /**
     * Enqueue CF7 Alpine components script
     */
    private static function enqueue_cf7_components_script(): void {
        if (!wp_script_is('cf7-alpine-components', 'enqueued')) {
            // Use built version with WordPress asset system
            $asset_file = get_stylesheet_directory() . '/build/js/cf7-components.asset.php';
            $asset = file_exists($asset_file) ? include $asset_file : ['dependencies' => [], 'version' => '1.0.0'];
            
            wp_enqueue_script(
                'cf7-alpine-components',
                get_stylesheet_directory_uri() . '/build/js/cf7-components.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
        }
    }

    /**
     * Localize script data for CF7 components
     */
    private static function localize_script_data(): void {
        // AJAX configuration
        wp_localize_script('cf7-alpine-components', 'realizaceAjax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mistr_fachman_access_control')
        ]);

        // Error messages
        wp_localize_script('cf7-alpine-components', 'realizaceErrorMessages', [
            'loading_failed' => 'Nepodařilo se načíst materiály. Zkuste to prosím znovu.',
            'network_error' => 'Chyba při komunikaci se serverem. Zkontrolujte připojení k internetu.'
        ]);
    }

}
