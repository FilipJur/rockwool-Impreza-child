<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Admin Setup - Custom Status & Admin UI
 *
 * Manages admin-facing UI and status registrations for the realizace post type.
 * Handles custom post status registration and admin list view enhancements.
 * Integrates with Users domain admin interface.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminSetup {

    public function __construct(
        private AdminCardRenderer $card_renderer,
        private AdminAssetManager $asset_manager
    ) {
        error_log('[REALIZACE:CONSTRUCT] AdminSetup constructor called');
        error_log('[REALIZACE:CONSTRUCT] Current hook: ' . (current_action() ?: 'none'));
        error_log('[REALIZACE:CONSTRUCT] Init hook done: ' . (did_action('init') ? 'yes' : 'no'));
        
        // If init has already happened, register immediately
        if (did_action('init')) {
            error_log('[REALIZACE:CONSTRUCT] Init already done - registering status immediately');
            $this->register_rejected_status();
        } else {
            error_log('[REALIZACE:CONSTRUCT] Init not done yet - registering hook');
            add_action('init', [$this, 'register_rejected_status'], 1);
        }
    }

    /**
     * Initialize admin hooks
     */
    public function init_hooks(): void {
        // Multiple registration attempts to ensure it works
        add_action('plugins_loaded', [$this, 'register_rejected_status'], 1);
        add_action('init', [$this, 'register_rejected_status'], 1);
        add_action('wp_loaded', [$this, 'verify_rejected_status'], 1);
        add_action('admin_footer-post.php', [$this, 'add_rejected_status_to_dropdown']);
        add_action('admin_footer-post-new.php', [$this, 'add_rejected_status_to_dropdown']);
        add_filter('manage_realizace_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_realizace_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_action('transition_post_status', [$this, 'handle_status_transition'], 10, 3);
        add_filter('display_post_states', [$this, 'add_post_state_labels'], 10, 2);
        add_action('save_post_realizace', [$this, 'handle_realizace_save'], 5, 2);
        
        // Integrate with Users domain admin interface
        add_action('mistr_fachman_user_profile_cards', [$this, 'add_realizace_cards_to_user_profile'], 10, 2);
        
        // AJAX handlers for quick actions
        add_action('wp_ajax_mistr_fachman_realizace_quick_action', [$this, 'handle_quick_action_ajax']);
        add_action('wp_ajax_mistr_fachman_bulk_approve_realizace', [$this, 'handle_bulk_approve_ajax']);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this->asset_manager, 'enqueue_admin_assets']);
    }

    /**
     * Register the custom 'rejected' post status
     */
    public function register_rejected_status(): void {
        static $registered = false;
        
        // Prevent multiple registrations
        if ($registered) {
            error_log('[REALIZACE:DEBUG] Rejected status already registered, skipping...');
            return;
        }
        
        error_log('[REALIZACE:DEBUG] Registering rejected status...');
        error_log('[REALIZACE:DEBUG] Current hook: ' . (current_action() ?: 'none'));
        error_log('[REALIZACE:DEBUG] WordPress loaded: ' . (did_action('wp_loaded') ? 'yes' : 'no'));
        
        // Check if status already exists (from another source)
        $existing_statuses = get_post_stati();
        if (isset($existing_statuses['rejected'])) {
            error_log('[REALIZACE:DEBUG] Rejected status already exists, marking as registered');
            $registered = true;
            return;
        }
        
        $status_args = [
            'label' => 'Odmítnuto',
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'protected' => true,  // This ensures it's not treated as public
            'private' => true,    // This marks it as private
            'publicly_queryable' => false,
            '_builtin' => false,  // Mark as custom status
            'label_count' => _n_noop(
                'Odmítnuto <span class="count">(%s)</span>',
                'Odmítnuto <span class="count">(%s)</span>'
            ),
        ];
        
        error_log('[REALIZACE:DEBUG] Rejected status args: ' . json_encode($status_args));
        
        $result = register_post_status('rejected', $status_args);
        
        error_log('[REALIZACE:DEBUG] register_post_status returned: ' . json_encode($result));
        
        // Verify registration immediately
        $registered_statuses = get_post_stati();
        if (isset($registered_statuses['rejected'])) {
            error_log('[REALIZACE:SUCCESS] Rejected status confirmed in registered statuses');
            $registered = true;
        } else {
            error_log('[REALIZACE:ERROR] Rejected status NOT found in registered statuses after registration');
            error_log('[REALIZACE:ERROR] Available statuses after registration: ' . implode(', ', array_keys($registered_statuses)));
        }
    }

    /**
     * Verify rejected status registration on wp_loaded
     */
    public function verify_rejected_status(): void {
        error_log('[REALIZACE:VERIFY] Verifying rejected status after wp_loaded...');
        
        $registered_statuses = get_post_stati();
        $available_keys = array_keys($registered_statuses);
        
        error_log('[REALIZACE:VERIFY] All registered statuses: ' . implode(', ', $available_keys));
        
        if (isset($registered_statuses['rejected'])) {
            error_log('[REALIZACE:VERIFY] SUCCESS: Rejected status found in registration');
            $rejected_status = $registered_statuses['rejected'];
            error_log('[REALIZACE:VERIFY] Rejected status properties: ' . json_encode([
                'label' => $rejected_status->label,
                'public' => $rejected_status->public,
                'show_in_admin_all_list' => $rejected_status->show_in_admin_all_list,
                'show_in_admin_status_list' => $rejected_status->show_in_admin_status_list
            ]));
        } else {
            error_log('[REALIZACE:ERROR] CRITICAL: Rejected status NOT found after wp_loaded!');
            error_log('[REALIZACE:ERROR] This will cause AJAX reject actions to fail');
        }
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
                console.log('[REALIZACE:DEBUG] Initializing rejected status dropdown support');
                
                // Add rejected status option
                var $statusSelect = $('select[name="post_status"]');
                console.log('[REALIZACE:DEBUG] Found status select elements:', $statusSelect.length);
                
                if ($statusSelect.length && !$statusSelect.find('option[value="rejected"]').length) {
                    $statusSelect.append('<option value="rejected">Odmítnuto</option>');
                    console.log('[REALIZACE:DEBUG] Added rejected option to status dropdown');
                }
                
                // Set current status if rejected
                <?php if ($post->post_status === 'rejected'): ?>
                console.log('[REALIZACE:DEBUG] Setting current status to rejected');
                $statusSelect.val('rejected');
                $('#post-status-display').text('Odmítnuto');
                
                // Also update any hidden inputs
                $('input[name="_status"]').val('rejected');
                console.log('[REALIZACE:DEBUG] Updated hidden status input to rejected');
                <?php endif; ?>
                
                // Handle status change display
                $statusSelect.on('change', function() {
                    var selectedStatus = $(this).val();
                    var displayText = $(this).find('option:selected').text();
                    console.log('[REALIZACE:DEBUG] Status changed to:', selectedStatus);
                    $('#post-status-display').text(displayText);
                    
                    // Ensure hidden input is also updated
                    $('input[name="_status"]').val(selectedStatus);
                });
                
                // Fix for Gutenberg editor
                if (window.wp && window.wp.data && window.wp.data.select('core/editor')) {
                    var postStatus = window.wp.data.select('core/editor').getEditedPostAttribute('status');
                    console.log('[REALIZACE:DEBUG] Gutenberg editor detected, current status:', postStatus);
                    if (postStatus === 'rejected') {
                        $('.editor-post-status .components-select-control__input').val('rejected');
                        console.log('[REALIZACE:DEBUG] Updated Gutenberg status to rejected');
                    }
                }
                
                // Intercept form submission to ensure rejected status is preserved
                $('#post').on('submit', function() {
                    var currentStatus = $statusSelect.val();
                    console.log('[REALIZACE:DEBUG] Form submitting with status:', currentStatus);
                    
                    if (currentStatus === 'rejected') {
                        // Ensure the POST data includes the rejected status
                        if (!$('input[name="post_status"][value="rejected"]').length) {
                            $(this).append('<input type="hidden" name="post_status" value="rejected">');
                            console.log('[REALIZACE:DEBUG] Added hidden input for rejected status');
                        }
                    }
                });
            });
            </script>
            <?php
        }
    }

    /**
     * Add custom columns to the admin list view
     * 
     * @param array $columns Existing columns
     * @return array Modified columns
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
     * 
     * @param string $column_name Column identifier
     * @param int $post_id Post ID
     */
    public function render_custom_columns(string $column_name, int $post_id): void {
        switch ($column_name) {
            case 'points':
                $points = get_post_meta($post_id, '_realizace_points_awarded', true);
                $points_value = $points ? (int)$points : 0;
                $color_class = $points_value > 0 ? 'text-green-600 font-medium' : 'text-gray-400';
                echo "<span class='{$color_class}'>{$points_value}</span>";
                break;
                
            case 'status':
                $status = get_post_status($post_id);
                $status_config = $this->get_admin_status_config($status);
                
                echo "<span class='inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {$status_config['class']}'>";
                echo esc_html($status_config['label']);
                echo '</span>';
                break;
        }
    }

    /**
     * Get admin status configuration for styling
     * 
     * @param string $status Post status
     * @return array Label and CSS class
     */
    private function get_admin_status_config(string $status): array {
        return match ($status) {
            'publish' => [
                'label' => 'Schváleno',
                'class' => 'bg-green-100 text-green-800'
            ],
            'pending' => [
                'label' => 'Čeká na schválení',
                'class' => 'bg-yellow-100 text-yellow-800'
            ],
            'rejected' => [
                'label' => 'Odmítnuto',
                'class' => 'bg-red-100 text-red-800'
            ],
            'draft' => [
                'label' => 'Koncept',
                'class' => 'bg-gray-100 text-gray-800'
            ],
            default => [
                'label' => ucfirst($status),
                'class' => 'bg-gray-100 text-gray-800'
            ]
        };
    }

    /**
     * Handle post status transitions for proper logging and validation
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param \WP_Post $post Post object
     */
    public function handle_status_transition(string $new_status, string $old_status, \WP_Post $post): void {
        if ($post->post_type !== 'realizace') {
            return;
        }

        // Log status transition for debugging
        error_log("[REALIZACE] Status transition: {$old_status} → {$new_status} for post #{$post->ID}");

        // Validate rejection reason when moving to rejected status
        if ($new_status === 'rejected' && $old_status !== 'rejected') {
            $rejection_reason = function_exists('get_field') 
                ? get_field('duvod_zamitnuti', $post->ID) 
                : get_post_meta($post->ID, 'duvod_zamitnuti', true);
            
            if (empty($rejection_reason)) {
                error_log("[REALIZACE] Warning: Post #{$post->ID} rejected without reason");
            }
        }

        // Clear any cached data when status changes
        wp_cache_delete("realizace_status_{$post->ID}", 'posts');
    }

    /**
     * Handle realizace save to preserve rejected status
     * 
     * This prevents WordPress from overriding custom statuses during save
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     */
    public function handle_realizace_save(int $post_id, \WP_Post $post): void {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        error_log("[REALIZACE:SAVE] Processing save for post {$post_id}");
        error_log("[REALIZACE:SAVE] Post status from object: {$post->post_status}");
        
        // Check if this is a status change via admin form
        $requested_status = $_POST['post_status'] ?? $_POST['_status'] ?? null;
        error_log("[REALIZACE:SAVE] Requested status from POST: " . ($requested_status ?: 'none'));
        
        // If user requested rejected status, ensure it's preserved
        if ($requested_status === 'rejected') {
            error_log("[REALIZACE:SAVE] User requested rejected status - preserving it");
            
            // Remove this hook temporarily to prevent infinite loop
            remove_action('save_post_realizace', [$this, 'handle_realizace_save'], 5);
            
            // Force the status update
            $result = wp_update_post([
                'ID' => $post_id,
                'post_status' => 'rejected'
            ], true);
            
            // Re-add the hook
            add_action('save_post_realizace', [$this, 'handle_realizace_save'], 5, 2);
            
            if (is_wp_error($result)) {
                error_log("[REALIZACE:ERROR] Failed to preserve rejected status: " . $result->get_error_message());
            } else {
                error_log("[REALIZACE:SUCCESS] Rejected status preserved for post {$post_id}");
            }
        }
        
        // Log final status
        $final_post = get_post($post_id);
        error_log("[REALIZACE:SAVE] Final post status: {$final_post->post_status}");
    }

    /**
     * Add custom post state labels in admin list view
     *
     * @param array $post_states Current post states
     * @param \WP_Post $post Post object
     * @return array Modified post states
     */
    public function add_post_state_labels(array $post_states, \WP_Post $post): array {
        if ($post->post_type !== 'realizace') {
            return $post_states;
        }

        if ($post->post_status === 'rejected') {
            $post_states['rejected'] = 'Odmítnuto';
        }

        return $post_states;
    }

    /**
     * Add realizace management cards to user profile
     * 
     * Hooks into the Users domain admin interface to display realizace-related
     * information and actions on user profile pages.
     *
     * @param \WP_User $user User object
     * @param bool $is_pending Whether user is pending approval
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
            echo '<div class="realizace-management-section">';
            
            $this->card_renderer->render_realizace_overview_card($user);
            $this->card_renderer->render_recent_realizace_card($user);
            $this->card_renderer->render_quick_actions_card($user);
            
            echo '</div>';
        }
    }

    /**
     * Handle AJAX quick actions for realizace
     */
    public function handle_quick_action_ajax(): void {
        error_log('[REALIZACE:DEBUG] AJAX quick action called');
        error_log('[REALIZACE:DEBUG] POST data: ' . json_encode($_POST));
        
        try {
            // Verify nonce and permissions
            check_ajax_referer('mistr_fachman_realizace_action', 'nonce');
            error_log('[REALIZACE:DEBUG] Nonce verified');
            
            if (!current_user_can('edit_posts')) {
                error_log('[REALIZACE:ERROR] User lacks edit_posts capability');
                wp_send_json_error(['message' => 'Insufficient permissions']);
            }
            
            $post_id = (int)($_POST['post_id'] ?? 0);
            $action = sanitize_text_field($_POST['realizace_action'] ?? '');
            
            error_log("[REALIZACE:DEBUG] Parameters: post_id={$post_id}, action={$action}");
            
            if (!$post_id || !in_array($action, ['approve', 'reject'])) {
                error_log('[REALIZACE:ERROR] Invalid parameters');
                wp_send_json_error(['message' => 'Invalid parameters']);
            }
            
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'realizace') {
                error_log('[REALIZACE:ERROR] Invalid post');
                wp_send_json_error(['message' => 'Invalid post']);
            }
            
            if ($action === 'approve') {
                // Get points to award from POST data
                $points_to_award = isset($_POST['points']) ? (int)$_POST['points'] : 0;
                
                // Validate points
                if ($points_to_award <= 0) {
                    wp_send_json_error(['message' => 'Počet bodů musí být kladné číslo.']);
                }
                
                // Save points to ACF field before updating status
                if (function_exists('update_field')) {
                    update_field('pridelene_body', $points_to_award, $post_id);
                } else {
                    update_post_meta($post_id, 'pridelene_body', $points_to_award);
                }
                
                // Now update post status - this will trigger the points award via PointsHandler
                $result = wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'publish'
                ]);
            } else {
                // REJECT ACTION DEBUGGING
                error_log("[REALIZACE:DEBUG] Processing REJECT action for post {$post_id}");
                error_log("[REALIZACE:DEBUG] Current post status before reject: {$post->post_status}");
                
                // Check if rejected status is available
                $available_statuses = get_post_stati(['show_in_admin_status_list' => true]);
                error_log("[REALIZACE:DEBUG] Available post statuses: " . json_encode(array_keys($available_statuses)));
                
                if (!isset($available_statuses['rejected'])) {
                    error_log("[REALIZACE:ERROR] Rejected status not available for post updates!");
                    error_log("[REALIZACE:EMERGENCY] Attempting emergency re-registration...");
                    
                    // Emergency re-registration
                    $this->register_rejected_status();
                    
                    // Check again
                    $available_statuses_retry = get_post_stati(['show_in_admin_status_list' => true]);
                    if (!isset($available_statuses_retry['rejected'])) {
                        error_log("[REALIZACE:CRITICAL] Emergency re-registration FAILED!");
                        wp_send_json_error(['message' => 'Chyba: Status "odmítnuto" není dostupný. Kontaktujte administrátora.']);
                    } else {
                        error_log("[REALIZACE:SUCCESS] Emergency re-registration succeeded!");
                    }
                }
                
                $result = wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'rejected'
                ]);
                
                error_log("[REALIZACE:DEBUG] wp_update_post result for reject: " . ($result ? 'SUCCESS' : 'FAILED'));
                
                if ($result) {
                    // Verify the status was actually set
                    $updated_post = get_post($post_id);
                    error_log("[REALIZACE:DEBUG] Post status after update: {$updated_post->post_status}");
                    
                    if ($updated_post->post_status !== 'rejected') {
                        error_log("[REALIZACE:ERROR] Status was not properly set to rejected! Got: {$updated_post->post_status}");
                    }
                }
                
                // Add default rejection reason if none exists
                $rejection_reason = get_post_meta($post_id, 'duvod_zamitnuti', true);
                if (empty($rejection_reason)) {
                    update_post_meta($post_id, 'duvod_zamitnuti', 'Rychle odmítnuto administrátorem');
                    error_log("[REALIZACE:DEBUG] Added default rejection reason");
                }
            }
            
            if (is_wp_error($result)) {
                error_log('[REALIZACE:ERROR] wp_update_post failed: ' . $result->get_error_message());
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
            
            // CRITICAL: Verify the post still exists and wasn't deleted
            $final_check_post = get_post($post_id);
            if (!$final_check_post) {
                error_log("[REALIZACE:CRITICAL] Post {$post_id} was DELETED during status update!");
                wp_send_json_error(['message' => 'Chyba: Příspěvek byl neočekávaně smazán.']);
            } else {
                error_log("[REALIZACE:DEBUG] Post {$post_id} still exists with status: {$final_check_post->post_status}");
            }
            
            error_log("[REALIZACE:INFO] Post {$post_id} successfully updated to {$action}");
            wp_send_json_success([
                'message' => $action === 'approve' ? 'Realizace byla schválena' : 'Realizace byla odmítnuta',
                'new_status' => $action === 'approve' ? 'publish' : 'rejected'
            ]);
            
        } catch (\Exception $e) {
            error_log('[REALIZACE:ERROR] Exception in quick action: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Chyba při zpracování: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle bulk approve AJAX action
     */
    public function handle_bulk_approve_ajax(): void {
        // Verify nonce and permissions
        check_ajax_referer('mistr_fachman_bulk_approve', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        if (!$user_id || !get_userdata($user_id)) {
            wp_send_json_error(['message' => 'Invalid user']);
        }
        
        try {
            // Get all pending realizace for user
            $pending_posts = get_posts([
                'post_type' => 'realizace',
                'author' => $user_id,
                'post_status' => 'pending',
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);
            
            $approved_count = 0;
            foreach ($pending_posts as $post_id) {
                $result = wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'publish'
                ]);
                
                if (!is_wp_error($result)) {
                    $approved_count++;
                }
            }
            
            wp_send_json_success([
                'message' => sprintf('Schváleno %d realizací', $approved_count),
                'approved_count' => $approved_count
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Chyba při hromadném schvalování: ' . $e->getMessage()]);
        }
    }

    /**
     * Check if user has any realizace posts
     *
     * @param int $user_id User ID
     * @return bool True if user has realizace posts
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

    /**
     * Validate and fix rejected status issues
     * 
     * This method can be called to diagnose and fix rejected status problems
     * 
     * @param int $post_id Optional post ID to check specific post
     * @return array Diagnostic information
     */
    public function validate_rejected_status(?int $post_id = null): array {
        error_log('[REALIZACE:VALIDATE] Starting rejected status validation');
        
        $diagnostics = [
            'status_registered' => false,
            'post_statuses' => [],
            'specific_post' => null,
            'issues' => [],
            'fixes_applied' => []
        ];
        
        // Check if rejected status is properly registered
        $registered_statuses = get_post_stati();
        $diagnostics['post_statuses'] = array_keys($registered_statuses);
        
        if (isset($registered_statuses['rejected'])) {
            $diagnostics['status_registered'] = true;
            error_log('[REALIZACE:VALIDATE] Rejected status is registered');
        } else {
            $diagnostics['issues'][] = 'Rejected status not registered';
            error_log('[REALIZACE:VALIDATE] ERROR: Rejected status not registered');
        }
        
        // Check specific post if provided
        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                $diagnostics['specific_post'] = [
                    'id' => $post->ID,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'exists' => true
                ];
                
                if ($post->post_status === 'rejected') {
                    error_log("[REALIZACE:VALIDATE] Post {$post_id} has rejected status correctly");
                } else {
                    error_log("[REALIZACE:VALIDATE] Post {$post_id} status is {$post->post_status}, not rejected");
                }
            } else {
                $diagnostics['specific_post'] = ['exists' => false];
                $diagnostics['issues'][] = "Post {$post_id} does not exist";
                error_log("[REALIZACE:VALIDATE] ERROR: Post {$post_id} does not exist");
            }
        }
        
        error_log('[REALIZACE:VALIDATE] Diagnostics: ' . json_encode($diagnostics));
        return $diagnostics;
    }

}