<?php

declare(strict_types=1);

namespace MistrFachman\Base;

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
     * Get the post type slug for this domain
     */
    abstract protected function getPostType(): string;

    /**
     * Get the display name for this domain
     */
    abstract protected function getDomainDisplayName(): string;

    /**
     * Get the default points for this domain
     */
    abstract protected function getDefaultPoints(int $post_id = 0): int;

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
     */
    abstract protected function getGalleryFieldSelector(): string;

    /**
     * Get the area/size field selector for this domain
     */
    abstract protected function getAreaFieldSelector(): string;

    /**
     * Get the construction type field selector for this domain
     */
    abstract protected function getConstructionTypeFieldSelector(): string;

    /**
     * Get the materials field selector for this domain
     */
    abstract protected function getMaterialsFieldSelector(): string;

    /**
     * Runs domain-specific validation checks before a post is published.
     * This method is the core of the validation gatekeeper.
     *
     * @param \WP_Post $post The post object being transitioned.
     * @return array An array with 'success' (bool) and 'message' (string) keys.
     *               e.g., ['success' => true] or ['success' => false, 'message' => 'Validation failed: Reason.']
     */
    abstract protected function run_pre_publish_validation(\WP_Post $post): array;

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

        // Post editor form customization
        add_action('admin_footer-post.php', [$this, 'add_rejected_status_to_dropdown']);
        add_action('admin_footer-post-new.php', [$this, 'add_rejected_status_to_dropdown']);

        // Status transition handling
        // Priority 5: Run validation gatekeeper BEFORE other transition logic
        add_action('transition_post_status', [$this, 'handle_pre_publish_validation'], 5, 3);
        add_action('transition_post_status', [$this, 'handle_status_transition'], 10, 3);

        // Admin notices for validation errors
        add_action('admin_notices', [$this, 'display_validation_admin_notices']);

        // User profile integration
        add_action('mistr_fachman_user_profile_cards', [$this, 'add_cards_to_user_profile'], 10, 2);
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
     */
    protected function init_ajax_hooks(): void {
        $post_type = $this->getPostType();
        
        add_action("wp_ajax_mistr_fachman_{$post_type}_quick_action", [$this, 'handle_quick_action_ajax']);
        add_action("wp_ajax_mistr_fachman_bulk_approve_{$post_type}", [$this, 'handle_bulk_approve_ajax']);
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
        $points_awarded = (int)get_post_meta($post_id, "_{$this->getPostType()}_points_awarded", true);

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
     * Get status display configuration
     */
    protected function get_status_display_config(string $status): array {
        return match ($status) {
            'publish' => [
                'label' => 'Schváleno',
                'class' => 'status-approved'
            ],
            'pending' => [
                'label' => 'Čeká na schválení',
                'class' => 'status-pending'
            ],
            'rejected' => [
                'label' => 'Odmítnuto',
                'class' => 'status-rejected'
            ],
            'draft' => [
                'label' => 'Koncept',
                'class' => 'status-draft'
            ],
            default => [
                'label' => ucfirst($status),
                'class' => 'status-default'
            ]
        };
    }

    /**
     * Handles the pre-publish validation check. Hooked to 'transition_post_status'.
     *
     * @param string   $new_status The new post status.
     * @param string   $old_status The old post status.
     * @param \WP_Post $post       The post object.
     */
    public function handle_pre_publish_validation(string $new_status, string $old_status, \WP_Post $post): void {
        // Only run for our specific post type and only when moving to 'publish'
        if ($post->post_type !== $this->getPostType() || $new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        // Call the domain-specific validation logic
        $validation_result = $this->run_pre_publish_validation($post);

        // If validation fails, block the transition
        if (isset($validation_result['success']) && $validation_result['success'] === false) {
            // 1. Force the status back to the original status to prevent publishing
            $post->post_status = $old_status;

            // 2. Set a transient to display a persistent admin notice after the page reloads
            $error_message = $validation_result['message'] ?? 'Schválení se nezdařilo z důvodu nesplnění pravidel.';
            set_transient(
                'domain_validation_error_' . get_current_user_id(),
                [
                    'message' => $error_message,
                    'post_title' => $post->post_title
                ],
                30 // Transient expires in 30 seconds
            );
        }
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
     * Handle AJAX quick actions
     */
    public function handle_quick_action_ajax(): void {
        try {
            // Verify nonce and permissions
            check_ajax_referer("mistr_fachman_{$this->getPostType()}_action", 'nonce');

            if (!current_user_can('edit_posts')) {
                throw new \Exception('Insufficient permissions');
            }

            $post_id = (int)($_POST['post_id'] ?? 0);
            // Support both legacy and new parameter names
            $action = sanitize_text_field($_POST['action_type'] ?? $_POST['realizace_action'] ?? '');

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
        // Verify nonce and permissions
        $nonce_action = "mistr_fachman_bulk_approve_{$this->getPostType()}";
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
                $default_points = $this->getDefaultPoints($post_id);
                $this->set_points($post_id, $default_points);
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
            check_ajax_referer("mistr_fachman_{$this->getPostType()}_action", 'nonce');

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
                'quick_action' => wp_create_nonce("mistr_fachman_{$this->getPostType()}_action"),
                'bulk_approve' => wp_create_nonce("mistr_fachman_bulk_approve_{$this->getPostType()}")
            ],
            'domain' => [
                'post_type' => $this->getPostType(),
                'display_name' => $this->getDomainDisplayName(),
                'default_points' => $this->getDefaultPoints()
            ]
        ];
    }
}