<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Admin Interface Handler - Single Source of Truth for Admin UI
 *
 * Handles ALL WordPress admin UI interactions for user management.
 * This class is the ONLY place where admin UI logic should be implemented.
 * 
 * IMPORTANT: This class is the single source of truth for:
 * - User profile form rendering (add_user_status_fields)
 * - User promotion handling (handle_promote_user_action) 
 * - Admin notice display (display_admin_notices)
 * - Admin hook registration (init_hooks)
 *
 * The Users\Manager class should NOT duplicate any admin UI functionality
 * that exists in this class. All admin UI logic flows through this class.
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
        private RegistrationHooks $registration_hooks
    ) {}

    /**
     * Initialize admin hooks
     */
    public function init_hooks(): void {
        // User profile hooks
        add_action('show_user_profile', [$this, 'add_user_status_fields']);
        add_action('edit_user_profile', [$this, 'add_user_status_fields']);
        add_action('personal_options_update', [$this, 'save_user_status_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_status_fields']);

        // Admin action hooks
        add_action('admin_post_mistr_fachman_promote_user', [$this, 'handle_promote_user_action']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }

    /**
     * Add user status fields to user profile page
     *
     * @param \WP_User $user User object
     */
    public function add_user_status_fields(\WP_User $user): void {
        // Only show for users with pending approval role
        if (!$this->role_manager->is_pending_user($user->ID)) {
            return;
        }

        $current_status = $this->role_manager->get_user_status($user->ID);
        $status_display = RegistrationStatus::getDisplayName($current_status);
        $css_class = RegistrationStatus::getCssClass($current_status);

        ?>
        <h3><?php esc_html_e('Stav registrace', 'mistr-fachman'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e('Aktuální stav', 'mistr-fachman'); ?></label></th>
                <td>
                    <span class="user-status <?php echo esc_attr($css_class); ?>">
                        <?php echo esc_html($status_display); ?>
                    </span>
                    <p class="description">
                        <?php echo esc_html($this->getStatusDescription($current_status)); ?>
                    </p>
                </td>
            </tr>
            
            <?php if ($current_status === RegistrationStatus::AWAITING_REVIEW): ?>
            <tr>
                <th><label><?php esc_html_e('Akce', 'mistr-fachman'); ?></label></th>
                <td>
                    <?php $this->render_promote_user_button($user->ID); ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <style>
        .user-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
        }
        .status-needs-form {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status-awaiting-review {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .promote-user-button {
            background-color: #007cba;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .promote-user-button:hover {
            background-color: #005a87;
            color: white;
        }
        </style>
        <?php
    }

    /**
     * Render promote user button with security nonce
     *
     * @param int $user_id User ID
     */
    private function render_promote_user_button(int $user_id): void {
        $promote_url = add_query_arg([
            'action' => 'mistr_fachman_promote_user',
            'user_id' => $user_id,
            '_wpnonce' => wp_create_nonce('mistr_fachman_promote_user_' . $user_id)
        ], admin_url('admin-post.php'));
        
        ?>
        <a href="<?php echo esc_url($promote_url); ?>" 
           class="promote-user-button"
           onclick="return confirm('<?php esc_attr_e('Opravdu chcete povýšit tohoto uživatele na plného člena?', 'mistr-fachman'); ?>');">
            <?php esc_html_e('Povýšit na plného člena', 'mistr-fachman'); ?>
        </a>
        
        <p class="description">
            <?php esc_html_e('Tato akce změní roli uživatele na "full_member" a odstraní registrační stav.', 'mistr-fachman'); ?>
        </p>
        <?php
    }

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
     * Get description for registration status
     *
     * @param string $status Registration status
     * @return string Status description
     */
    private function getStatusDescription(string $status): string {
        return match ($status) {
            RegistrationStatus::NEEDS_FORM => 'Uživatel se zaregistroval, ale ještě nevyplnil závěrečný formulář.',
            RegistrationStatus::AWAITING_REVIEW => 'Uživatel vyplnil formulář a čeká na schválení administrátorem.',
            RegistrationStatus::APPROVED => 'Uživatel byl schválen (legacy stav).',
            default => 'Neznámý stav registrace.'
        };
    }

    /**
     * Check if current screen is user edit page
     *
     * @return bool True if on user edit page
     */
    private function is_user_edit_screen(): bool {
        $screen = get_current_screen();
        return $screen && in_array($screen->id, ['user-edit', 'profile'], true);
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
}