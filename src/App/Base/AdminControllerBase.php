<?php

declare(strict_types=1);

namespace MistrFachman\Base;

use MistrFachman\Services\DomainConfigurationService;
use MistrFachman\Services\ProjectStatusService;


/**
 * Base Admin Controller - Abstract Foundation
 *
 * Provides common patterns for WordPress admin interface management.
 * Handles UI customizations, AJAX endpoints, and user profile integration.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class AdminControllerBase {

    /**
     * Get the post type slug for this domain (English, for internal logic)
     */
    abstract protected function getPostType(): string;

    /**
     * Get the WordPress post type slug (Czech, for database/WP operations)
     */
    abstract protected function getWordPressPostType(): string;


    /**
     * Get the display name for this domain
     */
    abstract protected function getDomainDisplayName(): string;

    /**
     * Get the calculated points for this domain
     * Can be static (like Realizace) or dynamic (like Faktury)
     */
    abstract protected function getCalculatedPoints(int $post_id = 0): int;

    /**
     * Get the points field selector (including group field prefix if applicable)
     */
    abstract protected function getPointsFieldSelector(): string;

    /**
     * Get the rejection reason field selector (including group field prefix if applicable)
     */
    abstract protected function getRejectionReasonFieldSelector(): string;

    /**
     * Get the gallery field selector for this domain
     * Override in child classes if needed
     */
    protected function getGalleryFieldSelector(): string {
        return '';
    }

    /**
     * Get the area/size field selector for this domain
     * Override in child classes if needed
     */
    protected function getAreaFieldSelector(): string {
        return '';
    }

    /**
     * Get the construction type field selector for this domain
     * Override in child classes if needed
     */
    protected function getConstructionTypeFieldSelector(): string {
        return '';
    }

    /**
     * Get the materials field selector for this domain
     * Override in child classes if needed
     */
    protected function getMaterialsFieldSelector(): string {
        return '';
    }


    /**
     * Render domain-specific user profile card
     */
    abstract protected function render_user_profile_card(\WP_User $user): void;

    /**
     * Process domain-specific approval logic
     */
    abstract protected function process_approve_action(int $post_id): void;

    /**
     * Process domain-specific rejection logic
     */
    abstract protected function process_reject_action(int $post_id, \WP_Post $post, string $rejection_reason = ''): void;

    /**
     * Initialize common admin hooks
     */
    public function init_common_hooks(): void {
        // Common UI customization hooks (generic across all domains)
        $this->init_common_ui_hooks();

        // Domain-specific UI customization hooks
        $this->init_ui_hooks();

        // AJAX endpoint hooks
        $this->init_ajax_hooks();
    }

    /**
     * Initialize common UI customization hooks (generic across all domains)
     */
    protected function init_common_ui_hooks(): void {
        // Admin list view customization
        add_filter('manage_' . $this->getPostType() . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . $this->getPostType() . '_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);

        // Post editor form customization - Reject button in publish box
        add_action('post_submitbox_misc_actions', [$this, 'add_reject_button_to_publish_box']);

        // Status transition handling
        add_action('transition_post_status', [$this, 'handle_status_transition'], 10, 3);

        // Admin notices for validation errors
        add_action('admin_notices', [$this, 'display_validation_admin_notices']);


        // User profile integration
        add_action('mistr_fachman_user_profile_cards', [$this, 'add_cards_to_user_profile'], 10, 2);

        // Users table customization - generic across all domains
        add_filter('manage_users_columns', [$this, 'add_pending_column_to_users_table']);
        add_action('manage_users_custom_column', [$this, 'render_pending_column_for_users_table'], 10, 3);
    }

    /**
     * Initialize domain-specific UI customization hooks
     * Override in child classes for domain-specific UI customizations
     */
    protected function init_ui_hooks(): void {
        // Override in child classes for domain-specific UI hooks
    }

    /**
     * Initialize AJAX hooks
     * Uses DomainRegistry for consistent action naming
     */
    protected function init_ajax_hooks(): void {
        $post_type = $this->getPostType();
        $domain_debug = strtoupper($post_type);

        $quick_action = "mistr_fachman_{$post_type}_quick_action";
        $bulk_action = "mistr_fachman_bulk_approve_{$post_type}";
        $editor_reject_action = "mistr_fachman_{$post_type}_editor_reject";

        error_log("[{$domain_debug}:AJAX] Registering AJAX action: wp_ajax_{$quick_action}");
        error_log("[{$domain_debug}:AJAX] Registering AJAX action: wp_ajax_{$bulk_action}");
        error_log("[{$domain_debug}:AJAX] Registering AJAX action: wp_ajax_{$editor_reject_action}");

        add_action("wp_ajax_{$quick_action}", [$this, 'handle_quick_action_ajax']);
        add_action("wp_ajax_{$bulk_action}", [$this, 'handle_bulk_approve_ajax']);
        add_action("wp_ajax_{$editor_reject_action}", [$this, 'handle_editor_reject_ajax']);
        add_action("wp_ajax_mistr_fachman_update_acf_field", [$this, 'handle_update_acf_field_ajax']);
    }

    /**
     * Add custom columns to admin list view
     */
    public function add_admin_columns(array $columns): array {
        $date = $columns['date'] ?? null;
        if ($date) {
            unset($columns['date']);
        }

        $columns['points'] = 'Přidělené body';
        $columns['status'] = 'Status';

        if ($date) {
            $columns['date'] = $date;
        }

        return $columns;
    }

    /**
     * Render custom column content
     */
    public function render_custom_columns(string $column, int $post_id): void {
        switch ($column) {
            case 'points':
                $this->render_points_column($post_id);
                break;
            case 'status':
                $this->render_status_column($post_id);
                break;
        }
    }

    /**
     * Render points column content
     */
    protected function render_points_column(int $post_id): void {
        $points_assigned = $this->get_current_points($post_id);
        $points_awarded = (int)get_post_meta($post_id, DomainConfigurationService::getFieldName($this->getPostType(), 'awarded_points_meta'), true);

        if ($points_assigned > 0) {
            echo '<strong>' . esc_html((string)$points_assigned) . '</strong>';
            if ($points_awarded && $points_awarded !== $points_assigned) {
                echo '<br><small>Uděleno: ' . esc_html((string)$points_awarded) . '</small>';
            }
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }

    /**
     * Render status column content
     */
    protected function render_status_column(int $post_id): void {
        $post = get_post($post_id);
        if (!$post) return;

        $status_config = $this->get_status_display_config($post->post_status);

        echo '<span class="status-indicator status-' . esc_attr($post->post_status) . '">';
        echo '<span class="status-dot ' . esc_attr($status_config['class']) . '"></span>';
        echo esc_html($status_config['label']);
        echo '</span>';
    }

    /**
     * Get status display configuration using ProjectStatusService
     */
    protected function get_status_display_config(string $status): array {
        // Get status label from ProjectStatusService
        $label = ProjectStatusService::getStatusLabel($status);
        
        // Map status to admin-specific CSS classes
        $class = match ($status) {
            'publish' => 'status-approved',
            'pending' => 'status-pending', 
            'rejected' => 'status-rejected',
            'draft' => 'status-draft',
            default => 'status-default'
        };
        
        return [
            'label' => $label,
            'class' => $class
        ];
    }

    /**
     * Add context-aware reject/revoke button to post editor publish box
     */
    public function add_reject_button_to_publish_box(): void {
        global $post;
        
        if (!$post) {
            return;
        }

        // Only show for our specific post types
        if ($post->post_type !== $this->getPostType()) {
            return;
        }

        // Determine button text, style, and behavior based on status
        $button_text = 'Odmítnout';
        $button_style = 'background: #d63638; border-color: #b62d2f; color: #fff;'; // Red for reject
        $button_disabled = '';
        $action_type = 'reject';

        if ($post->post_status === 'publish') {
            $button_text = 'Zrušit schválení';
            $button_style = 'background: #f0ad4e; border-color: #eea236; color: #fff;'; // Orange for revoke
            $action_type = 'revoke';
        } elseif ($post->post_status === 'rejected') {
            $button_text = ProjectStatusService::getStatusLabel('rejected');
            $button_style = 'background: #7e8993; border-color: #7e8993; color: #fff; cursor: not-allowed;'; // Grey for rejected
            $button_disabled = 'disabled';
            $action_type = 'disabled';
        }

        // Generate a unique nonce for this action
        $nonce = wp_create_nonce('mistr_fachman_editor_reject_nonce_' . $post->ID);
        ?>
        <div class="misc-pub-section misc-pub-section-reject">
            <button type="button" 
                    id="mistr-fachman-reject-button" 
                    class="button button-large" 
                    data-post-id="<?php echo esc_attr($post->ID); ?>" 
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    data-action-type="<?php echo esc_attr($action_type); ?>"
                    style="width: 100%; margin-top: 10px; <?php echo esc_attr($button_style); ?>"
                    <?php echo $button_disabled; ?>>
                <?php echo esc_html($button_text); ?>
            </button>
        </div>
        <?php
    }

    /**
     * Displays the validation error admin notice stored in a transient.
     * Hooked to 'admin_notices'.
     */
    public function display_validation_admin_notices(): void {
        $user_id = get_current_user_id();
        $transient_key = 'domain_validation_error_' . $user_id;
        $error_data = get_transient($transient_key);

        if ($error_data && is_array($error_data)) {
            $message = sprintf(
                '<strong>Chyba validace pro "%s":</strong> %s',
                esc_html($error_data['post_title']),
                esc_html($error_data['message'])
            );

            // Display the admin notice
            printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', $message);

            // Delete the transient so it doesn't show again
            delete_transient($transient_key);
        }
    }

    /**
     * Add pending posts column to users table
     */
    public function add_pending_column_to_users_table(array $columns): array {
        // Insert the pending column before the "Posts" column if it exists,
        // otherwise before the "Date" column
        $insert_before = isset($columns['posts']) ? 'posts' : 'date';
        $column_key = 'pending_' . $this->getPostType();
        $column_label = 'Čekající ' . $this->getDomainDisplayName();

        // Find the position to insert the new column
        $position = array_search($insert_before, array_keys($columns));
        if ($position !== false) {
            $columns = array_slice($columns, 0, $position, true) +
                      [$column_key => $column_label] +
                      array_slice($columns, $position, null, true);
        } else {
            // Fallback: add at the end
            $columns[$column_key] = $column_label;
        }

        return $columns;
    }

    /**
     * Render pending posts column content for users table
     */
    public function render_pending_column_for_users_table(string $output, string $column_name, int $user_id): string {
        $expected_column = 'pending_' . $this->getPostType();

        if ($column_name === $expected_column) {
            return $this->render_pending_posts_column($user_id);
        }

        return $output;
    }

    /**
     * Render pending posts column content
     */
    protected function render_pending_posts_column(int $user_id): string {
        // Get pending posts count for this user
        $pending_count = $this->get_user_pending_posts_count($user_id);

        if ($pending_count === 0) {
            return '<span style="color: #999;">0</span>';
        }

        // Make the count clickable to filter posts for this user
        $admin_url = admin_url('edit.php');
        $filter_url = add_query_arg([
            'post_type' => $this->getPostType(),
            'post_status' => 'pending',
            'author' => $user_id
        ], $admin_url);

        return sprintf(
            '<a href="%s" style="color: #d63638; font-weight: 600;" title="Zobrazit čekající %s tohoto uživatele">%d</a>',
            esc_url($filter_url),
            strtolower($this->getDomainDisplayName()),
            $pending_count
        );
    }

    /**
     * Get pending posts count for a specific user
     */
    protected function get_user_pending_posts_count(int $user_id): int {
        static $cache = [];
        $cache_key = $this->getPostType() . '_' . $user_id;

        // Use static cache to avoid multiple queries for the same user during page load
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        global $wpdb;

        $count = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = %s
            AND post_author = %d
            AND post_status = 'pending'
        ", $this->getPostType(), $user_id));

        $cache[$cache_key] = $count;
        return $count;
    }

    /**
     * Add cards to user profile
     */
    public function add_cards_to_user_profile(\WP_User $user, bool $is_pending): void {
        // Only show for eligible users
        if (!$is_pending && !in_array('full_member', $user->roles)) {
            return;
        }

        // Check if user has any posts of this type
        if ($this->user_has_posts($user->ID) || $is_pending || in_array('full_member', $user->roles)) {
            $this->render_user_profile_card($user);
        }
    }

    /**
     * Check if user has posts of this type
     */
    protected function user_has_posts(int $user_id): bool {
        $posts = get_posts([
            'post_type' => $this->getPostType(),
            'author' => $user_id,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        return !empty($posts);
    }

    /**
     * Validate domain configuration - simplified without DomainRegistry
     * Basic validation that post type is properly configured
     */
    protected function validateDomainConfiguration(): void {
        $post_type = $this->getPostType();

        if (empty($post_type)) {
            error_log("ERROR: Post type is empty or not properly configured");
            return;
        }

        // Log successful validation
        $domain_debug = strtoupper($post_type);
        error_log("[{$domain_debug}:CONFIG] Domain configuration validated successfully");
    }

    /**
     * Get nonce action using direct string concatenation
     */
    protected function getNonceAction(string $action_type = 'quick_action'): string {
        $post_type = $this->getPostType();

        return match ($action_type) {
            'quick_action' => "mistr_fachman_{$post_type}_quick_action",
            'bulk_approve' => "mistr_fachman_bulk_approve_{$post_type}",
            default => "mistr_fachman_{$post_type}_{$action_type}"
        };
    }

    /**
     * Handle AJAX quick actions
     */
    public function handle_quick_action_ajax(): void {
        error_log("AJAX Handler 'handle_quick_action_ajax' started for domain: " . $this->getPostType());
        error_log("POST data: " . json_encode($_POST));

        try {
            // Validate domain configuration
            $this->validateDomainConfiguration();

            // IMPORTANT: The nonce action *must* match the one registered in the hook.
            $nonce_action = "mistr_fachman_" . $this->getPostType() . "_quick_action";
            error_log("Verifying nonce against action: " . $nonce_action);

            check_ajax_referer($nonce_action, 'nonce');
            error_log("Nonce VERIFIED successfully.");

            if (!current_user_can('edit_posts')) {
                throw new \Exception('Insufficient permissions');
            }

            $post_id = (int)($_POST['post_id'] ?? 0);
            // Support both legacy and new parameter names (domain-specific action parameters)
            $post_type = $this->getPostType();
            $action = sanitize_text_field($_POST['action_type'] ?? $_POST['realizace_action'] ?? $_POST["{$post_type}_quick_action"] ?? $_POST["{$post_type}_action"] ?? '');

            if (!$post_id || !in_array($action, ['approve', 'reject'])) {
                throw new \Exception('Invalid parameters');
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== $this->getPostType()) {
                throw new \Exception('Invalid post');
            }

            // Delegate domain-specific logic to child class
            if ($action === 'approve') {
                $this->process_approve_action($post_id);
            } else {
                $rejection_reason = sanitize_textarea_field($_POST['rejection_reason'] ?? '');
                $this->process_reject_action($post_id, $post, $rejection_reason);
            }

            // Send success response from base class
            wp_send_json_success([
                'message' => $action === 'approve' ? 'Akce byla úspěšně provedena.' : 'Položka byla zamítnuta.',
                'new_status' => $action === 'approve' ? 'publish' : 'rejected'
            ]);

        } catch (\Exception $e) {
            error_log('[' . strtoupper($this->getPostType()) . ':AJAX] Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Chyba při zpracování: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle bulk approve AJAX action
     */
    public function handle_bulk_approve_ajax(): void {
        // Validate domain configuration and verify nonce using DomainRegistry
        $this->validateDomainConfiguration();
        $nonce_action = $this->getNonceAction('bulk_approve');
        $nonce_value = $_POST['nonce'] ?? '';

        if (!wp_verify_nonce($nonce_value, $nonce_action)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $user_id = (int)($_POST['user_id'] ?? 0);
        if (!$user_id || !get_userdata($user_id)) {
            wp_send_json_error(['message' => 'Invalid user']);
        }

        try {
            $approved_count = $this->process_bulk_approve($user_id);
            wp_send_json_success([
                'message' => sprintf('Schváleno %d %s', $approved_count, $this->getDomainDisplayName()),
                'approved_count' => $approved_count
            ]);
        } catch (\Exception $e) {
            error_log('[' . strtoupper($this->getPostType()) . ':AJAX] Bulk approve exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Chyba při hromadném schvalování: ' . $e->getMessage()]);
        }
    }

    /**
     * Process bulk approve operation
     */
    protected function process_bulk_approve(int $user_id): int {
        $pending_posts = get_posts([
            'post_type' => $this->getPostType(),
            'author' => $user_id,
            'post_status' => 'pending',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        $approved_count = 0;
        foreach ($pending_posts as $post_id) {
            // SINGLE SOURCE OF TRUTH: Use abstract methods for all domains
            $current_points = $this->get_current_points($post_id);
            if ($current_points === 0) {
                $calculated_points = $this->getCalculatedPoints($post_id);
                $this->set_points($post_id, $calculated_points);
            } else {
                // Points already set manually - respect the existing value
            }

            // Approve the post
            $result = wp_update_post([
                'ID' => $post_id,
                'post_status' => 'publish'
            ]);

            if (!is_wp_error($result)) {
                $approved_count++;
            }
        }

        return $approved_count;
    }

    /**
     * Handle ACF field update AJAX
     */
    public function handle_update_acf_field_ajax(): void {
        try {
            // Validate domain configuration and use DomainRegistry for nonce
            $this->validateDomainConfiguration();
            $nonce_action = $this->getNonceAction('quick_action');
            check_ajax_referer($nonce_action, 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
            }

            $post_id = (int)($_POST['post_id'] ?? 0);
            $field_name = sanitize_text_field($_POST['field_name'] ?? '');
            $field_value = sanitize_textarea_field($_POST['field_value'] ?? '');

            if (!$post_id || !$field_name) {
                wp_send_json_error(['message' => 'Invalid parameters']);
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== $this->getPostType()) {
                wp_send_json_error(['message' => 'Invalid post']);
            }

            // Update the ACF field
            if (function_exists('update_field')) {
                $result = update_field($field_name, $field_value, $post_id);
            } else {
                $result = update_post_meta($post_id, $field_name, $field_value);
            }

            if ($result !== false) {
                wp_send_json_success([
                    'message' => 'Field updated successfully',
                    'field_name' => $field_name,
                    'field_value' => $field_value
                ]);
            } else {
                wp_send_json_error(['message' => 'Failed to update field']);
            }

        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Error updating field: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle dedicated editor reject AJAX action
     */
    public function handle_editor_reject_ajax(): void {
        try {
            $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
            $nonce = $_POST['nonce'] ?? '';

            if (!wp_verify_nonce($nonce, 'mistr_fachman_editor_reject_nonce_' . $post_id)) {
                throw new \Exception('Invalid security token.');
            }

            if (!current_user_can('edit_post', $post_id)) {
                throw new \Exception('Insufficient permissions.');
            }

            $rejection_reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : ProjectStatusService::getStatusLabel('rejected') . ' administrátorem z editoru.';

            // Use the existing field service to set the reason
            $field_selector = $this->getRejectionReasonFieldSelector();
            if (function_exists('update_field')) {
                update_field($field_selector, $rejection_reason, $post_id);
            } else {
                update_post_meta($post_id, $field_selector, $rejection_reason);
            }

            // Update the post status to 'rejected'
            wp_update_post([
                'ID' => $post_id,
                'post_status' => 'rejected',
            ]);

            wp_send_json_success([
                'message' => 'Položka byla úspěšně odmítnuta.',
                'redirect_url' => get_edit_post_link($post_id, 'raw'),
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Get current points value for a post
     */
    public function get_current_points(int $post_id): int {
        if (function_exists('get_field')) {
            $points = get_field($this->getPointsFieldSelector(), $post_id);
        } else {
            $points = get_post_meta($post_id, $this->getPointsFieldSelector(), true);
        }

        return is_numeric($points) ? (int)$points : 0;
    }

    /**
     * Set points value for a post
     */
    protected function set_points(int $post_id, int $points): bool {
        if (function_exists('update_field')) {
            $result = update_field($this->getPointsFieldSelector(), $points, $post_id);
            return $result !== false;
        } else {
            return update_post_meta($post_id, $this->getPointsFieldSelector(), $points) !== false;
        }
    }

    /**
     * Get localized data for JavaScript
     */
    public function get_localized_data(): array {
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonces' => [
                'quick_action' => wp_create_nonce("mistr_fachman_{$this->getPostType()}_quick_action"),
                'bulk_approve' => wp_create_nonce("mistr_fachman_bulk_approve_{$this->getPostType()}")
            ],
            'domain' => [
                'post_type' => $this->getPostType(),
                'display_name' => $this->getDomainDisplayName(),
                'default_points' => $this->getCalculatedPoints()
            ]
        ];
    }
}
