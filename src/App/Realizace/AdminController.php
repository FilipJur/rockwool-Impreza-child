<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Admin Controller - Unified Admin Interface Controller
 *
 * Manages all admin-side realizace functionality with clear separation of concerns:
 *
 * RESPONSIBILITIES:
 * - Status management coordination
 * - UI customizations (columns, forms, user profiles, users table integration)
 * - AJAX endpoints (quick actions, bulk operations)
 * - Asset management coordination
 *
 * ARCHITECTURAL PRINCIPLE: Single cohesive controller with logical sections,
 * avoiding over-decomposition while maintaining clear responsibility boundaries.
 *
 * FIXED ISSUES:
 * - Status revert bug (removed save_post hook that fought WordPress core)
 * - Bulk approve logic (properly processes points from UI)
 * - Inline CSS violations (moved to SCSS files)
 *
 * RECENT ADDITIONS:
 * - Users table "Pending Realizace" column with clickable counts
 * - Efficient query caching for user pending counts
 * - Asset loading extended to users.php page
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminController {

    public function __construct(
        private StatusManager $status_manager,
        private AdminCardRenderer $card_renderer,
        private AdminAssetManager $asset_manager
    ) {
        error_log('[REALIZACE:ADMIN] AdminController initialized with unified architecture');

        // Smart status registration based on WordPress lifecycle
        if (did_action('init')) {
            error_log('[REALIZACE:ADMIN] Init already done - registering status immediately');
            $this->status_manager->register_rejected_status();
        } else {
            error_log('[REALIZACE:ADMIN] Registering init hook for status');
            add_action('init', [$this->status_manager, 'register_rejected_status'], 1);
        }
    }

    /**
     * Initialize all admin hooks with clear responsibility sections
     */
    public function init_hooks(): void {
        error_log('[REALIZACE:ADMIN] Initializing unified admin hooks');

        // Status management hooks
        $this->status_manager->init_hooks();

        // UI customization hooks
        $this->init_ui_hooks();

        // AJAX endpoint hooks
        $this->init_ajax_hooks();

        // Asset management hooks
        add_action('admin_enqueue_scripts', [$this->asset_manager, 'enqueue_admin_assets']);

        error_log('[REALIZACE:ADMIN] All admin hooks initialized');
    }

    // ===========================================
    // UI MANAGEMENT SECTION
    // Responsibility: Admin interface customizations
    // ===========================================

    /**
     * Initialize UI customization hooks
     */
    private function init_ui_hooks(): void {
        // Admin list view customization
        add_filter('manage_realizace_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_realizace_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);

        // Users table customization
        add_filter('manage_users_columns', [$this, 'add_users_columns']);
        add_action('manage_users_custom_column', [$this, 'render_users_custom_column'], 10, 3);

        // Post editor form customization
        add_action('admin_footer-post.php', [$this, 'add_rejected_status_to_dropdown']);
        add_action('admin_footer-post-new.php', [$this, 'add_rejected_status_to_dropdown']);

        // Status transition handling (NO save_post hook - this was the bug!)
        add_action('transition_post_status', [$this, 'handle_status_transition'], 10, 3);

        // User profile integration
        add_action('mistr_fachman_user_profile_cards', [$this, 'add_realizace_cards_to_user_profile'], 10, 2);
    }

    /**
     * Add custom columns to the admin list view
     */
    public function add_admin_columns(array $columns): array {
        // Insert new columns before the date column
        $date = $columns['date'];
        unset($columns['date']);

        $columns['points'] = 'Přidělené body';
        $columns['status'] = 'Status';
        $columns['date'] = $date;

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
    private function render_points_column(int $post_id): void {
        $points_assigned = function_exists('get_field')
            ? get_field('pridelene_body', $post_id)
            : get_post_meta($post_id, 'pridelene_body', true);

        $points_awarded = (int)get_post_meta($post_id, '_realizace_points_awarded', true);

        if ($points_assigned) {
            echo '<strong>' . esc_html((string)$points_assigned) . '</strong>';
            if ($points_awarded && $points_awarded !== (int)$points_assigned) {
                echo '<br><small>Uděleno: ' . esc_html((string)$points_awarded) . '</small>';
            }
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }

    /**
     * Render status column content
     */
    private function render_status_column(int $post_id): void {
        $post = get_post($post_id);
        $status_config = $this->get_status_display_config($post->post_status);

        echo '<span class="status-indicator status-' . esc_attr($post->post_status) . '">';
        echo '<span class="status-dot ' . esc_attr($status_config['class']) . '"></span>';
        echo esc_html($status_config['label']);
        echo '</span>';
    }

    /**
     * Get status display configuration
     */
    private function get_status_display_config(string $status): array {
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
     * Add custom columns to the users table
     */
    public function add_users_columns(array $columns): array {
        // Insert the "Pending Realizace" column before the "Posts" column if it exists,
        // otherwise before the "Date" column
        $insert_before = isset($columns['posts']) ? 'posts' : 'date';
        
        // Find the position to insert the new column
        $position = array_search($insert_before, array_keys($columns));
        if ($position !== false) {
            $columns = array_slice($columns, 0, $position, true) +
                      ['pending_realizace' => 'Čekající realizace'] +
                      array_slice($columns, $position, null, true);
        } else {
            // Fallback: add at the end
            $columns['pending_realizace'] = 'Čekající realizace';
        }

        return $columns;
    }

    /**
     * Render custom column content for users table
     */
    public function render_users_custom_column(string $output, string $column_name, int $user_id): string {
        if ($column_name === 'pending_realizace') {
            return $this->render_pending_realizace_column($user_id);
        }
        return $output;
    }

    /**
     * Render pending realizace column content
     */
    private function render_pending_realizace_column(int $user_id): string {
        // Get pending realizace count for this user
        $pending_count = $this->get_user_pending_realizace_count($user_id);
        
        if ($pending_count === 0) {
            return '<span style="color: #999;">0</span>';
        }

        // Make the count clickable to filter realizace posts for this user
        $admin_url = admin_url('edit.php');
        $filter_url = add_query_arg([
            'post_type' => 'realizace',
            'post_status' => 'pending',
            'author' => $user_id
        ], $admin_url);

        return sprintf(
            '<a href="%s" style="color: #d63638; font-weight: 600;" title="Zobrazit čekající realizace tohoto uživatele">%d</a>',
            esc_url($filter_url),
            $pending_count
        );
    }

    /**
     * Get pending realizace count for a specific user
     */
    private function get_user_pending_realizace_count(int $user_id): int {
        static $cache = [];
        
        // Use static cache to avoid multiple queries for the same user during page load
        if (isset($cache[$user_id])) {
            return $cache[$user_id];
        }

        global $wpdb;
        
        $count = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'realizace'
            AND post_author = %d
            AND post_status = 'pending'
        ", $user_id));

        $cache[$user_id] = $count;
        return $count;
    }

    /**
     * Add rejected status to the post editor's status dropdown
     */
    public function add_rejected_status_to_dropdown(): void {
        global $post;

        if ($post && $post->post_type === 'realizace') {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                console.log('[REALIZACE:UI] Initializing rejected status dropdown support');

                // Add rejected status option
                var $statusSelect = $('select[name="post_status"]');
                console.log('[REALIZACE:UI] Found status select elements:', $statusSelect.length);

                if ($statusSelect.length && !$statusSelect.find('option[value="rejected"]').length) {
                    $statusSelect.append('<option value="rejected">Odmítnuto</option>');
                    console.log('[REALIZACE:UI] Added rejected option to status dropdown');
                }

                // Set current status if rejected
                <?php if ($post->post_status === 'rejected'): ?>
                console.log('[REALIZACE:UI] Setting current status to rejected');
                $statusSelect.val('rejected');
                $('#post-status-display').text('Odmítnuto');

                // Also update any hidden inputs
                $('input[name="_status"]').val('rejected');
                console.log('[REALIZACE:UI] Updated hidden status input to rejected');
                <?php endif; ?>

                // Handle status change display
                $statusSelect.on('change', function() {
                    var selectedStatus = $(this).val();
                    var displayText = $(this).find('option:selected').text();
                    console.log('[REALIZACE:UI] Status changed to:', selectedStatus);
                    $('#post-status-display').text(displayText);

                    // Ensure hidden input is also updated
                    $('input[name="_status"]').val(selectedStatus);
                });

                // Fix for Gutenberg editor
                if (window.wp && window.wp.data && window.wp.data.select('core/editor')) {
                    var postStatus = window.wp.data.select('core/editor').getEditedPostAttribute('status');
                    console.log('[REALIZACE:UI] Gutenberg editor detected, current status:', postStatus);
                    if (postStatus === 'rejected') {
                        $('.editor-post-status .components-select-control__input').val('rejected');
                        console.log('[REALIZACE:UI] Updated Gutenberg status to rejected');
                    }
                }

                // Intercept form submission to ensure rejected status is preserved
                $('#post').on('submit', function() {
                    var currentStatus = $statusSelect.val();
                    console.log('[REALIZACE:UI] Form submitting with status:', currentStatus);

                    if (currentStatus === 'rejected') {
                        // Ensure the POST data includes the rejected status
                        if (!$('input[name="post_status"][value="rejected"]').length) {
                            $(this).append('<input type="hidden" name="post_status" value="rejected">');
                            console.log('[REALIZACE:UI] Added hidden input for rejected status');
                        }
                    }
                });
            });
            </script>
            <?php
        }
    }

    /**
     * Handle status transitions for logging and validation
     */
    public function handle_status_transition(string $new_status, string $old_status, \WP_Post $post): void {
        if ($post->post_type !== 'realizace') {
            return;
        }

        error_log("[REALIZACE:UI] Status transition: {$old_status} → {$new_status} for post #{$post->ID}");

        // Validate rejection reason when moving to rejected status
        if ($new_status === 'rejected' && $old_status !== 'rejected') {
            $rejection_reason = function_exists('get_field')
                ? get_field('duvod_zamitnuti', $post->ID)
                : get_post_meta($post->ID, 'duvod_zamitnuti', true);

            if (empty($rejection_reason)) {
                error_log("[REALIZACE:UI] Warning: Post #{$post->ID} rejected without reason");
            }
        }

        // Clear any cached data when status changes
        wp_cache_delete("realizace_status_{$post->ID}", 'posts');
    }

    /**
     * Add realizace management cards to user profile
     */
    public function add_realizace_cards_to_user_profile(\WP_User $user, bool $is_pending): void {
        // Only show realizace cards for users who can submit realizace
        // (pending users and full members)
        if (!$is_pending && !in_array('full_member', $user->roles)) {
            return;
        }

        // Check if user has any realizace posts
        $has_realizace = $this->user_has_realizace($user->ID);

        // Show cards if user has realizace or is eligible to submit them
        if ($has_realizace || $is_pending || in_array('full_member', $user->roles)) {
            echo '<div class="realizace-management-modern">';
            
            // Consolidated dashboard replacing the dual-card layout
            $this->card_renderer->render_consolidated_realizace_dashboard($user);
            
            echo '</div>';
        }
    }

    /**
     * Check if user has any realizace posts
     */
    private function user_has_realizace(int $user_id): bool {
        $posts = get_posts([
            'post_type' => 'realizace',
            'author' => $user_id,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        return !empty($posts);
    }

    // ===========================================
    // AJAX MANAGEMENT SECTION
    // Responsibility: AJAX endpoint processing
    // ===========================================

    /**
     * Initialize AJAX hooks
     */
    private function init_ajax_hooks(): void {
        add_action('wp_ajax_mistr_fachman_realizace_quick_action', [$this, 'handle_quick_action_ajax']);
        add_action('wp_ajax_mistr_fachman_bulk_approve_realizace', [$this, 'handle_bulk_approve_ajax']);
        add_action('wp_ajax_mistr_fachman_update_acf_field', [$this, 'handle_update_acf_field_ajax']);
    }

    /**
     * Handle AJAX quick actions for realizace (approve/reject)
     */
    public function handle_quick_action_ajax(): void {
        error_log('[REALIZACE:AJAX] Quick action called');
        error_log('[REALIZACE:AJAX] POST data: ' . json_encode($_POST));

        try {
            // Verify nonce and permissions
            check_ajax_referer('mistr_fachman_realizace_action', 'nonce');
            error_log('[REALIZACE:AJAX] Nonce verified');

            if (!current_user_can('edit_posts')) {
                error_log('[REALIZACE:AJAX] User lacks edit_posts capability');
                wp_send_json_error(['message' => 'Insufficient permissions']);
            }

            $post_id = (int)($_POST['post_id'] ?? 0);
            $action = sanitize_text_field($_POST['realizace_action'] ?? '');

            error_log("[REALIZACE:AJAX] Parameters: post_id={$post_id}, action={$action}");

            if (!$post_id || !in_array($action, ['approve', 'reject'])) {
                error_log('[REALIZACE:AJAX] Invalid parameters');
                wp_send_json_error(['message' => 'Invalid parameters']);
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'realizace') {
                error_log('[REALIZACE:AJAX] Invalid post');
                wp_send_json_error(['message' => 'Invalid post']);
            }

            if ($action === 'approve') {
                $this->process_approve_action($post_id);
            } else {
                // Get rejection reason from request
                $rejection_reason = sanitize_textarea_field($_POST['rejection_reason'] ?? '');
                $this->process_reject_action($post_id, $post, $rejection_reason);
            }

        } catch (\Exception $e) {
            error_log('[REALIZACE:AJAX] Exception in quick action: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Chyba při zpracování: ' . $e->getMessage()]);
        }
    }

    /**
     * Process approve action with points validation
     */
    private function process_approve_action(int $post_id): void {
        error_log("[REALIZACE:AJAX] Processing APPROVE action for post {$post_id}");

        // Get points to award from POST data
        $points_to_award = isset($_POST['points']) ? (int)$_POST['points'] : 0;

        // Validate points
        if ($points_to_award <= 0) {
            wp_send_json_error(['message' => 'Počet bodů musí být kladné číslo.']);
        }

        // Save points to ACF field before updating status
        if (function_exists('update_field')) {
            /** @var callable $update_field */
            $update_field = 'update_field';
            $update_field('pridelene_body', $points_to_award, $post_id);
        } else {
            update_post_meta($post_id, 'pridelene_body', $points_to_award);
        }

        error_log("[REALIZACE:AJAX] Set points to {$points_to_award} for post {$post_id}");

        // Clear rejection reason when approving (clean slate for approved realizace)
        if (function_exists('update_field')) {
            /** @var callable $update_field */
            $update_field = 'update_field';
            $update_field('duvod_zamitnuti', '', $post_id);
            error_log("[REALIZACE:AJAX] Cleared rejection reason for post {$post_id}");
        } else {
            update_post_meta($post_id, 'duvod_zamitnuti', '');
        }

        // Now update post status - this will trigger the points award via PointsHandler
        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'publish'
        ]);

        $this->handle_update_result($result, $post_id, 'approve');
    }

    /**
     * Process reject action with comprehensive status validation
     */
    private function process_reject_action(int $post_id, \WP_Post $post, string $rejection_reason = ''): void {
        error_log("[REALIZACE:AJAX] Processing REJECT action for post {$post_id}");
        error_log("[REALIZACE:AJAX] Current post status before reject: {$post->post_status}");

        // Check if rejected status is available
        if (!$this->status_manager->is_rejected_status_available()) {
            error_log("[REALIZACE:AJAX] Rejected status not available - attempting emergency registration");

            if (!$this->status_manager->emergency_register_rejected_status()) {
                wp_send_json_error(['message' => 'Chyba: Status "odmítnuto" není dostupný. Kontaktujte administrátora.']);
            }
        }

        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'rejected'
        ]);

        error_log("[REALIZACE:AJAX] wp_update_post result for reject: " . ($result ? 'SUCCESS' : 'FAILED'));

        if ($result) {
            // Verify the status was actually set
            $updated_post = get_post($post_id);
            error_log("[REALIZACE:AJAX] Post status after update: {$updated_post->post_status}");

            if ($updated_post->post_status !== 'rejected') {
                error_log("[REALIZACE:AJAX] ERROR: Status was not properly set to rejected! Got: {$updated_post->post_status}");
            }
        }

        // Set rejection reason from user input or use default
        if (!empty($rejection_reason)) {
            update_post_meta($post_id, 'duvod_zamitnuti', $rejection_reason);
            error_log("[REALIZACE:AJAX] Set user-provided rejection reason: {$rejection_reason}");
        } else {
            // Only set default if no existing reason and no user input
            $existing_reason = get_post_meta($post_id, 'duvod_zamitnuti', true);
            if (empty($existing_reason)) {
                update_post_meta($post_id, 'duvod_zamitnuti', 'Rychle odmítnuto administrátorem');
                error_log("[REALIZACE:AJAX] Added default rejection reason");
            }
        }

        $this->handle_update_result($result, $post_id, 'reject');
    }

    /**
     * Handle the result of wp_update_post and send appropriate response
     */
    private function handle_update_result($result, int $post_id, string $action): void {
        if (is_wp_error($result)) {
            error_log('[REALIZACE:AJAX] wp_update_post failed: ' . $result->get_error_message());
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // CRITICAL: Verify the post still exists and wasn't deleted
        $final_check_post = get_post($post_id);
        if (!$final_check_post) {
            error_log("[REALIZACE:AJAX] CRITICAL: Post {$post_id} was DELETED during status update!");
            wp_send_json_error(['message' => 'Chyba: Příspěvek byl neočekávaně smazán.']);
        } else {
            error_log("[REALIZACE:AJAX] Post {$post_id} still exists with status: {$final_check_post->post_status}");
        }

        error_log("[REALIZACE:AJAX] Post {$post_id} successfully updated to {$action}");
        wp_send_json_success([
            'message' => $action === 'approve' ? 'Realizace byla schválena' : 'Realizace byla odmítnuta',
            'new_status' => $action === 'approve' ? 'publish' : 'rejected'
        ]);
    }

    /**
     * Handle bulk approve AJAX action with improved points collection
     */
    public function handle_bulk_approve_ajax(): void {
        error_log('[REALIZACE:AJAX] Bulk approve called');

        // Verify nonce and permissions
        check_ajax_referer('mistr_fachman_bulk_approve', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $user_id = (int)($_POST['user_id'] ?? 0);
        $points_data_json = $_POST['points_data'] ?? '{}';

        error_log('[REALIZACE:AJAX] Bulk approve raw POST data: ' . json_encode($_POST));

        if (!$user_id || !get_userdata($user_id)) {
            wp_send_json_error(['message' => 'Invalid user']);
        }

        // Parse points data - handle potential escaping from FormData
        $points_data = json_decode(stripslashes($points_data_json), true);
        if (!is_array($points_data)) {
            // Try without stripslashes in case it's not escaped
            $points_data = json_decode($points_data_json, true);
            if (!is_array($points_data)) {
                $points_data = [];
            }
        }

        error_log('[REALIZACE:AJAX] Points data JSON: ' . $points_data_json);
        error_log('[REALIZACE:AJAX] Points data with stripslashes: ' . stripslashes($points_data_json));
        error_log('[REALIZACE:AJAX] Points data parsed: ' . json_encode($points_data));

        try {
            $approved_count = $this->process_bulk_approve($user_id, $points_data);

            wp_send_json_success([
                'message' => sprintf('Schváleno %d realizací', $approved_count),
                'approved_count' => $approved_count
            ]);

        } catch (\Exception $e) {
            error_log('[REALIZACE:AJAX] Bulk approve exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Chyba při hromadném schvalování: ' . $e->getMessage()]);
        }
    }

    /**
     * Process bulk approve operation for a user with proper points handling
     */
    private function process_bulk_approve(int $user_id, array $points_data = []): int {
        // Get all pending realizace for user
        $pending_posts = get_posts([
            'post_type' => 'realizace',
            'author' => $user_id,
            'post_status' => 'pending',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        error_log("[REALIZACE:AJAX] Found " . count($pending_posts) . " pending posts for user {$user_id}");

        $approved_count = 0;
        foreach ($pending_posts as $post_id) {
            // Get points for this specific post from the UI data
            $points = isset($points_data[$post_id]) ? (int)$points_data[$post_id] : 0;

            // Skip posts without points specified
            if ($points <= 0) {
                error_log("[REALIZACE:AJAX] Skipping post {$post_id} - no points specified (got: {$points})");
                continue;
            }

            // Set points to ACF field first
            if (function_exists('update_field')) {
                /** @var callable $update_field */
                $update_field = 'update_field';
                $update_field('pridelene_body', $points, $post_id);
            } else {
                update_post_meta($post_id, 'pridelene_body', $points);
            }

            error_log("[REALIZACE:AJAX] Set points to {$points} for post {$post_id}");

            // Clear rejection reason when approving (clean slate for approved realizace)
            if (function_exists('update_field')) {
                /** @var callable $update_field */
                $update_field = 'update_field';
                $update_field('duvod_zamitnuti', '', $post_id);
            } else {
                update_post_meta($post_id, 'duvod_zamitnuti', '');
            }

            // Now update status - this will trigger the points award via PointsHandler
            $result = wp_update_post([
                'ID' => $post_id,
                'post_status' => 'publish'
            ]);

            if (!is_wp_error($result)) {
                error_log("[REALIZACE:AJAX] Bulk approved post {$post_id} with {$points} points");
                $approved_count++;
            } else {
                error_log("[REALIZACE:AJAX] Failed to bulk approve post {$post_id}: " . $result->get_error_message());
            }
        }

        return $approved_count;
    }

    /**
     * Handle ACF field update AJAX action
     */
    public function handle_update_acf_field_ajax(): void {
        error_log('[REALIZACE:AJAX] Update ACF field called');
        error_log('[REALIZACE:AJAX] POST data: ' . json_encode($_POST));

        try {
            // Verify nonce and permissions
            check_ajax_referer('mistr_fachman_realizace_action', 'nonce');

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
            if (!$post || $post->post_type !== 'realizace') {
                wp_send_json_error(['message' => 'Invalid post']);
            }

            // Update the ACF field
            /** @var callable $update_field */
            $update_field = 'update_field';
            $result = $update_field($field_name, $field_value, $post_id);

            if ($result !== false) {
                error_log("[REALIZACE:AJAX] Updated ACF field '{$field_name}' for post {$post_id}");
                wp_send_json_success([
                    'message' => 'Field updated successfully',
                    'field_name' => $field_name,
                    'field_value' => $field_value
                ]);
            } else {
                error_log("[REALIZACE:AJAX] Failed to update ACF field '{$field_name}' for post {$post_id}");
                wp_send_json_error(['message' => 'Failed to update field']);
            }

        } catch (\Exception $e) {
            error_log('[REALIZACE:AJAX] Exception in update ACF field: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error updating field: ' . $e->getMessage()]);
        }
    }

    // ===========================================
    // UTILITY AND ACCESSOR METHODS
    // Responsibility: Helper methods and external access
    // ===========================================

    /**
     * Get the status manager instance
     */
    public function get_status_manager(): StatusManager {
        return $this->status_manager;
    }

    /**
     * Validate rejected status implementation (delegated to StatusManager)
     */
    public function validate_rejected_status(?int $post_id = null): array {
        return $this->status_manager->validate_status_implementation($post_id);
    }
}
