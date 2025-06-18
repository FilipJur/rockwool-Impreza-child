<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Admin Action Handler - Admin Action Processing
 *
 * Handles all WordPress admin action processing for user management.
 * Centralizes promotion, revocation, and notice display logic.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminActionHandler {

    public function __construct(
        private RoleManager $role_manager,
        private RegistrationHooks $registration_hooks
    ) {}

    /**
     * Handle promote user admin action
     */
    public function handle_promote_user_action(): void {
        // Verify user capabilities
        if (!current_user_can('edit_users')) {
            wp_die(esc_html__('Nemáte oprávnění k této akci.', 'mistr-fachman'));
        }

        // Get and validate user ID from GET parameters
        $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        if (!$user_id) {
            wp_die(esc_html__('Neplatné ID uživatele.', 'mistr-fachman'));
        }

        // Verify nonce with the correct action name
        $nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING);
        if (!wp_verify_nonce($nonce, 'mistr_fachman_promote_user_' . $user_id)) {
            wp_die(esc_html__('Bezpečnostní kontrola selhala.', 'mistr-fachman'));
        }

        // Verify user exists and has pending role
        $user = get_user_by('ID', $user_id);
        if (!$user || !$this->role_manager->is_pending_user($user_id)) {
            wp_die(esc_html__('Uživatel nebyl nalezen nebo nemá čekající stav.', 'mistr-fachman'));
        }

        // Promote user
        $success = $this->registration_hooks->promote_to_full_member($user_id);

        if ($success) {
            // Set success message
            set_transient('mistr_fachman_admin_notice', [
                'type' => 'success',
                'message' => sprintf(
                    esc_html__('Uživatel %s byl úspěšně povýšen na plného člena.', 'mistr-fachman'),
                    $user->display_name
                )
            ], 30);

            mycred_debug('User promoted to full member via admin action', [
                'user_id' => $user_id,
                'user_login' => $user->user_login,
                'promoted_by' => get_current_user_id()
            ], 'users', 'info');
        } else {
            // Set error message
            set_transient('mistr_fachman_admin_notice', [
                'type' => 'error',
                'message' => esc_html__('Chyba při povyšování uživatele. Zkuste to znovu.', 'mistr-fachman')
            ], 30);

            mycred_debug('Failed to promote user via admin action', [
                'user_id' => $user_id,
                'user_login' => $user->user_login,
                'attempted_by' => get_current_user_id()
            ], 'users', 'error');
        }

        // Redirect back to user edit page
        $redirect_url = add_query_arg('user_id', $user_id, admin_url('user-edit.php'));
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Handle revoke user admin action
     */
    public function handle_revoke_user_action(): void {
        // Verify user capabilities
        if (!current_user_can('edit_users')) {
            wp_die(esc_html__('Nemáte oprávnění k této akci.', 'mistr-fachman'));
        }

        // Get and validate user ID from GET parameters
        $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        if (!$user_id) {
            wp_die(esc_html__('Neplatné ID uživatele.', 'mistr-fachman'));
        }

        // Verify nonce with the correct action name
        $nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING);
        if (!wp_verify_nonce($nonce, 'mistr_fachman_revoke_user_' . $user_id)) {
            wp_die(esc_html__('Bezpečnostní kontrola selhala.', 'mistr-fachman'));
        }

        // Verify user exists and is a full member
        $user = get_user_by('ID', $user_id);
        if (!$user || !in_array(RoleManager::FULL_MEMBER, $user->roles, true)) {
            wp_die(esc_html__('Uživatel nebyl nalezen nebo není plným členem.', 'mistr-fachman'));
        }

        // Revoke user
        $success = $this->registration_hooks->revoke_to_pending($user_id);

        if ($success) {
            // Set success message
            set_transient('mistr_fachman_admin_notice', [
                'type' => 'success',
                'message' => sprintf(
                    esc_html__('Členství uživatele %s bylo zrušeno a uživatel byl vrácen do stavu čekání na schválení.', 'mistr-fachman'),
                    $user->display_name
                )
            ], 30);

            mycred_debug('User membership revoked via admin action', [
                'user_id' => $user_id,
                'user_login' => $user->user_login,
                'revoked_by' => get_current_user_id()
            ], 'users', 'info');
        } else {
            // Set error message
            set_transient('mistr_fachman_admin_notice', [
                'type' => 'error',
                'message' => esc_html__('Chyba při rušení členství uživatele. Zkuste to znovu.', 'mistr-fachman')
            ], 30);

            mycred_debug('Failed to revoke user membership via admin action', [
                'user_id' => $user_id,
                'user_login' => $user->user_login,
                'attempted_by' => get_current_user_id()
            ], 'users', 'error');
        }

        // Redirect back to user edit page
        $redirect_url = add_query_arg('user_id', $user_id, admin_url('user-edit.php'));
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices(): void {
        $notice = get_transient('mistr_fachman_admin_notice');
        
        if (!$notice || !is_array($notice)) {
            return;
        }

        $type = $notice['type'] ?? 'info';
        $message = $notice['message'] ?? '';

        if (empty($message)) {
            return;
        }

        $css_class = match ($type) {
            'success' => 'notice-success',
            'error' => 'notice-error',
            'warning' => 'notice-warning',
            default => 'notice-info'
        };

        ?>
        <div class="notice <?php echo esc_attr($css_class); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php

        // Clear the notice
        delete_transient('mistr_fachman_admin_notice');
    }

    /**
     * Save user status fields (currently read-only, but hook for future)
     *
     * @param int $user_id User ID
     */
    public function save_user_status_fields(int $user_id): void {
        // Currently, status fields are read-only
        // This method exists for future extensibility
        
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        // Future: Handle manual status updates if needed
        mycred_debug('User profile updated', [
            'user_id' => $user_id,
            'updated_by' => get_current_user_id(),
            'note' => 'Status fields are currently read-only'
        ], 'users', 'verbose');
    }

    /**
     * Get admin capabilities required for user management
     *
     * @return array Required capabilities
     */
    public function get_required_capabilities(): array {
        return [
            'edit_users',
            'promote_users',
            'manage_options'
        ];
    }

    /**
     * Check if current screen is user edit page
     *
     * @return bool True if on user edit page
     */
    public function is_user_edit_screen(): bool {
        $screen = get_current_screen();
        return $screen && in_array($screen->id, ['user-edit', 'profile'], true);
    }
}