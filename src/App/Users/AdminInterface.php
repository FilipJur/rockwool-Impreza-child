<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Admin Interface Handler - Coordinating Admin UI Components
 *
 * Coordinates and orchestrates admin UI components for user management.
 * Acts as a facade for specialized UI, styling, and action handling classes.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminInterface {

    public function __construct(
        private RoleManager $role_manager,
        private RegistrationHooks $registration_hooks,
        private AdminStyleManager $style_manager,
        private AdminCardRenderer $card_renderer,
        private AdminAssetManager $asset_manager,
        private AdminActionHandler $action_handler,
        private BusinessDataModal $business_modal,
        private BusinessDataValidator $business_validator,
        private BusinessDataManager $business_manager
    ) {}

    /**
     * Initialize admin hooks
     */
    public function init_hooks(): void {
        error_log('AdminInterface::init_hooks called');

        // User profile hooks
        add_action('show_user_profile', [$this, 'add_user_status_fields']);
        add_action('edit_user_profile', [$this, 'add_user_status_fields']);
        add_action('personal_options_update', [$this->action_handler, 'save_user_status_fields']);
        add_action('edit_user_profile_update', [$this->action_handler, 'save_user_status_fields']);

        // Admin action hooks
        add_action('admin_post_mistr_fachman_promote_user', [$this->action_handler, 'handle_promote_user_action']);
        add_action('admin_post_mistr_fachman_revoke_user', [$this->action_handler, 'handle_revoke_user_action']);
        add_action('admin_notices', [$this->action_handler, 'display_admin_notices']);

        // AJAX hooks for business data modal
        add_action('wp_ajax_mistr_fachman_get_business_data', [$this->business_modal, 'handle_get_business_data_ajax']);

        // AJAX hook for fresh nonce generation
        add_action('wp_ajax_mistr_fachman_get_fresh_nonce', [$this, 'handle_get_fresh_nonce_ajax']);

        // AJAX hook for IČO validation
        add_action('wp_ajax_mistr_fachman_validate_ico', [$this, 'handle_ico_validation_ajax']);
        // This makes the endpoint accessible to users with custom roles who may not be considered 'admin' users by WordPress
        add_action('wp_ajax_nopriv_mistr_fachman_validate_ico', [$this, 'handle_ico_validation_ajax']);

        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', [$this->asset_manager, 'enqueue_admin_assets']);
        error_log('AdminInterface: admin_enqueue_scripts hook registered');
    }

    /**
     * Add user status fields to user profile page
     *
     * @param \WP_User $user User object
     */
    public function add_user_status_fields(\WP_User $user): void {
        // Show for all business users (pending or full members who came through registration)
        $is_pending = $this->role_manager->is_pending_user($user->ID);
        $has_business_data = $this->registration_hooks->has_business_data($user->ID);

        // Show section if user is pending OR has business data (promoted users)
        if (!$is_pending && !$has_business_data) {
            return;
        }

        ?>
        <div class="mistr-user-management-section">
            <h3 class="section-title">
                <span class="title-icon">🏢</span>
                <?php esc_html_e('Správa obchodního účtu', 'mistr-fachman'); ?>
            </h3>

            <div class="management-grid">
                <?php $this->card_renderer->render_user_status_card($user, $is_pending); ?>
                <?php $this->card_renderer->render_business_data_card($user); ?>
                <?php $this->card_renderer->render_admin_actions_card($user, $is_pending); ?>
                <?php $this->card_renderer->render_future_features_card($user); ?>
            </div>
        </div>

        <?php $this->style_manager->render_admin_styles(); ?>

        <?php
        // Always render business data modal for users with business data
        if ($has_business_data) {
            $this->business_modal->render_business_data_modal();
        }
    }

    /**
     * Get admin capabilities required for user management
     *
     * @return array Required capabilities
     */
    public function get_required_capabilities(): array {
        return $this->action_handler->get_required_capabilities();
    }

    /**
     * Check if current screen is user edit page
     *
     * @return bool True if on user edit page
     */
    public function is_user_edit_screen(): bool {
        return $this->action_handler->is_user_edit_screen();
    }

    /**
     * Handle AJAX request for fresh nonce generation
     */
    public function handle_get_fresh_nonce_ajax(): void {
        // Verify user capabilities
        if (!current_user_can('edit_users')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Get action parameter
        $action = $_POST['action_name'] ?? 'mistr_fachman_business_data';

        // Generate fresh nonce
        $fresh_nonce = wp_create_nonce($action);

        error_log('Fresh nonce generated: ' . $fresh_nonce . ' for action: ' . $action);

        wp_send_json_success([
            'nonce' => $fresh_nonce,
            'action' => $action
        ]);
    }

    /**
     * Handle AJAX request for IČO validation.
     * This endpoint is public but secured by a nonce.
     * Works for both logged-in and logged-out users.
     */
    public function handle_ico_validation_ajax(): void {
        try {
            // Security Check 1: Nonce is now the primary security gate for all users.
            check_ajax_referer('mistr_fachman_ico_validation_nonce', 'nonce');

            $ico = sanitize_text_field($_POST['ico'] ?? '');

            if (empty($ico)) {
                throw new \Exception('IČO nebylo zadáno.', 400);
            }

            // Step 1: Validate Format (for everyone)
            if (!$this->business_validator->validate_ico_format($ico)) {
                throw new \Exception('Neplatný formát IČO.', 422);
            }

            // Step 2: Perform USER-SPECIFIC checks ONLY if the user is logged in.
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                // Business Rule: As per new requirements, we no longer check for IČO uniqueness.
                // This block remains for future user-specific logic.
            }

            // Step 3: Validate with ARES API (for everyone)
            $ares_result = $this->business_validator->validate_ico_with_ares($ico);
            if (!$ares_result['valid']) {
                throw new \Exception($ares_result['error'], 422);
            }

            // All checks passed. Return success.
            wp_send_json_success([
                'company_name' => $ares_result['data']['obchodniJmeno'],
                'address'      => $this->extract_ares_address_from_result($ares_result['data']),
            ]);

        } catch (\Exception $e) {
            $code = is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 400;
            wp_send_json_error(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * Extract formatted address from ARES data
     * @param array $ares_data ARES API response data
     * @return string Formatted address
     */
    private function extract_ares_address_from_result(array $ares_data): string {
        if (!isset($ares_data['sidlo'])) {
            return '';
        }

        $sidlo = $ares_data['sidlo'];

        // Check if textovaAdresa exists (preferred format)
        if (isset($sidlo['textovaAdresa']) && !empty($sidlo['textovaAdresa'])) {
            return $sidlo['textovaAdresa'];
        }

        // Build address from components
        $address_parts = [];

        // Street and number
        if (isset($sidlo['nazevUlice'])) {
            $street = $sidlo['nazevUlice'];
            if (isset($sidlo['cisloDomovni'])) {
                $street .= ' ' . $sidlo['cisloDomovni'];
                if (isset($sidlo['cisloOrientacni'])) {
                    $street .= '/' . $sidlo['cisloOrientacni'];
                }
            }
            $address_parts[] = $street;
        }

        // City and postal code
        if (isset($sidlo['nazevObce'])) {
            $city_line = $sidlo['nazevObce'];
            if (isset($sidlo['psc'])) {
                $city_line = $sidlo['psc'] . ' ' . $city_line;
            }
            $address_parts[] = $city_line;
        }

        return implode(', ', $address_parts);
    }
}
