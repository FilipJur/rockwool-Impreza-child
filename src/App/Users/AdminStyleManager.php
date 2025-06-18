<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Admin Style Manager - Admin Styling Coordinator
 *
 * Coordinates admin styling needs. Styles are now managed via 
 * SCSS partials and the build process for better maintainability.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminStyleManager {

    /**
     * Render admin styles placeholder
     * 
     * Styles are now handled by SCSS partials:
     * - src/scss/components/_admin-interface.scss
     * - src/scss/components/_business-data-modal.scss
     * 
     * These are compiled into the main theme stylesheet via build process.
     */
    public function render_admin_styles(): void {
        // Admin styles are now handled via SCSS build process
        // No inline styles needed - everything is in compiled CSS
        
        // Add nonce meta tag for JavaScript AJAX requests
        $nonce = wp_create_nonce('mistr_fachman_business_data');
        echo '<meta name="wp-nonce-mistr_fachman_business_data" content="' . esc_attr($nonce) . '" />';
    }
}