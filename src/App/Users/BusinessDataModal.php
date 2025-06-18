<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Business Data Modal - Modal Structure and AJAX
 *
 * Handles business data modal HTML structure and AJAX interactions.
 * Styling is handled via SCSS partials and JavaScript via ES6 modules.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BusinessDataModal {

    public function __construct(
        private RegistrationHooks $registration_hooks,
        private BusinessDataModalRenderer $modal_renderer
    ) {}

    /**
     * Render business data modal HTML structure
     */
    public function render_business_data_modal(): void {
        ?>
        <div id="business-data-modal" class="mistr-modal" style="display: none;">
            <div class="mistr-modal-backdrop"></div>
            <div class="mistr-modal-content">
                <div class="mistr-modal-header">
                    <h2><?php esc_html_e('Přehled firemních údajů', 'mistr-fachman'); ?></h2>
                    <button type="button" class="mistr-modal-close">&times;</button>
                </div>
                <div class="mistr-modal-body">
                    <div class="mistr-modal-loading">
                        <p><?php esc_html_e('Načítání firemních údajů...', 'mistr-fachman'); ?></p>
                    </div>
                    <div class="mistr-modal-data" style="display: none;"></div>
                </div>
                <div class="mistr-modal-footer">
                    <button type="button" class="button mistr-modal-close">
                        <?php esc_html_e('Zavřít', 'mistr-fachman'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle AJAX request for business data
     */
    public function handle_get_business_data_ajax(): void {
        // Debug logging
        error_log('BusinessDataModal AJAX called');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('Current user can edit_users: ' . (current_user_can('edit_users') ? 'YES' : 'NO'));
        
        // Verify user capabilities
        if (!current_user_can('edit_users')) {
            error_log('AJAX Error: Insufficient permissions');
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Verify nonce - check both 'nonce' and '_wpnonce' parameters
        $nonce = $_POST['nonce'] ?? $_POST['_wpnonce'] ?? '';
        $nonce_valid = wp_verify_nonce($nonce, 'mistr_fachman_business_data');
        
        error_log('Nonce received: ' . $nonce);
        error_log('Nonce valid: ' . ($nonce_valid ? 'YES' : 'NO'));
        
        // Test: Generate a new nonce here and compare
        $test_nonce = wp_create_nonce('mistr_fachman_business_data');
        error_log('Test nonce generated now: ' . $test_nonce);
        error_log('Current user ID: ' . get_current_user_id());
        error_log('Nonces match: ' . ($nonce === $test_nonce ? 'YES' : 'NO'));
        
        // Debug WordPress nonce mechanism
        error_log('WordPress nonce tick: ' . wp_nonce_tick());
        error_log('WordPress nonce user: ' . wp_get_current_user()->ID);
        
        // Nonce validation - should now work with fresh nonces
        if (!$nonce_valid) {
            error_log('AJAX Error: Invalid nonce');
            wp_send_json_error(['message' => 'Invalid nonce verification']);
        }

        // Get user ID
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        error_log('User ID received: ' . ($user_id ?: 'INVALID'));
        
        if (!$user_id) {
            error_log('AJAX Error: Invalid user ID');
            wp_send_json_error(['message' => 'Invalid user ID']);
        }

        // Get business data
        $business_data = $this->registration_hooks->get_business_data($user_id);
        if (!$business_data) {
            error_log('AJAX Error: No business data found for user ' . $user_id);
            wp_send_json_error(['message' => 'Firemní údaje nenalezeny']);
        }

        error_log('Business data found for user ' . $user_id);

        // Generate HTML for modal content
        $html = $this->modal_renderer->generate_business_data_html($business_data);
        
        error_log('Generated HTML length: ' . strlen($html));
        wp_send_json_success(['html' => $html]);
    }
}