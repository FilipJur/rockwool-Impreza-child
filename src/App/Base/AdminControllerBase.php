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
     * Get the points field name
     */
    abstract protected function getPointsFieldName(): string;

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
        // UI customization hooks
        $this->init_ui_hooks();
        
        // AJAX endpoint hooks
        $this->init_ajax_hooks();
    }

    /**
     * Initialize UI customization hooks
     */
    protected function init_ui_hooks(): void {
        // Admin list view customization
        add_filter('manage_' . $this->getPostType() . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . $this->getPostType() . '_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);

        // Post editor form customization
        add_action('admin_footer-post.php', [$this, 'add_rejected_status_to_dropdown']);
        add_action('admin_footer-post-new.php', [$this, 'add_rejected_status_to_dropdown']);

        // Status transition handling
        add_action('transition_post_status', [$this, 'handle_status_transition'], 10, 3);

        // User profile integration
        add_action('mistr_fachman_user_profile_cards', [$this, 'add_cards_to_user_profile'], 10, 2);
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
        error_log('[' . strtoupper($this->getPostType()) . ':AJAX] Quick action called');

        try {
            // Verify nonce and permissions
            check_ajax_referer("mistr_fachman_{$this->getPostType()}_action", 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
            }

            $post_id = (int)($_POST['post_id'] ?? 0);
            $action = sanitize_text_field($_POST['action_type'] ?? '');

            if (!$post_id || !in_array($action, ['approve', 'reject'])) {
                wp_send_json_error(['message' => 'Invalid parameters']);
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== $this->getPostType()) {
                wp_send_json_error(['message' => 'Invalid post']);
            }

            if ($action === 'approve') {
                $this->process_approve_action($post_id);
            } else {
                $rejection_reason = sanitize_textarea_field($_POST['rejection_reason'] ?? '');
                $this->process_reject_action($post_id, $post, $rejection_reason);
            }

        } catch (\Exception $e) {
            error_log('[' . strtoupper($this->getPostType()) . ':AJAX] Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Chyba při zpracování: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle bulk approve AJAX action
     */
    public function handle_bulk_approve_ajax(): void {
        error_log('[' . strtoupper($this->getPostType()) . ':AJAX] Bulk approve called');

        // Verify nonce and permissions
        check_ajax_referer("mistr_fachman_bulk_approve_{$this->getPostType()}", 'nonce');

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
            // Ensure default points are set
            $current_points = $this->get_current_points($post_id);
            if ($current_points === 0) {
                $this->set_points($post_id, $this->getDefaultPoints($post_id));
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
    protected function get_current_points(int $post_id): int {
        if (function_exists('get_field')) {
            $points = get_field($this->getPointsFieldName(), $post_id);
        } else {
            $points = get_post_meta($post_id, $this->getPointsFieldName(), true);
        }

        return is_numeric($points) ? (int)$points : 0;
    }

    /**
     * Set points value for a post
     */
    protected function set_points(int $post_id, int $points): bool {
        if (function_exists('update_field')) {
            $result = update_field($this->getPointsFieldName(), $points, $post_id);
            return $result !== false;
        } else {
            return update_post_meta($post_id, $this->getPointsFieldName(), $points) !== false;
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