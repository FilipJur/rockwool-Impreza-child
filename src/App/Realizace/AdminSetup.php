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
    ) {}

    /**
     * Initialize admin hooks
     */
    public function init_hooks(): void {
        add_action('init', [$this, 'register_rejected_status']);
        add_action('admin_footer-post.php', [$this, 'add_rejected_status_to_dropdown']);
        add_action('admin_footer-post-new.php', [$this, 'add_rejected_status_to_dropdown']);
        add_filter('manage_realizace_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_realizace_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_action('transition_post_status', [$this, 'handle_status_transition'], 10, 3);
        add_filter('display_post_states', [$this, 'add_post_state_labels'], 10, 2);
        
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
        register_post_status('rejected', [
            'label' => 'Odmítnuto',
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Odmítnuto <span class="count">(%s)</span>',
                'Odmítnuto <span class="count">(%s)</span>'
            ),
        ]);
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
                // Add rejected status option
                var $statusSelect = $('select[name="post_status"]');
                if ($statusSelect.length && !$statusSelect.find('option[value="rejected"]').length) {
                    $statusSelect.append('<option value="rejected">Odmítnuto</option>');
                }
                
                // Set current status if rejected
                <?php if ($post->post_status === 'rejected'): ?>
                $statusSelect.val('rejected');
                $('#post-status-display').text('Odmítnuto');
                <?php endif; ?>
                
                // Handle status change display
                $statusSelect.on('change', function() {
                    var selectedStatus = $(this).val();
                    var displayText = $(this).find('option:selected').text();
                    $('#post-status-display').text(displayText);
                });
                
                // Fix for Gutenberg editor
                if (window.wp && window.wp.data && window.wp.data.select('core/editor')) {
                    var postStatus = window.wp.data.select('core/editor').getEditedPostAttribute('status');
                    if (postStatus === 'rejected') {
                        $('.editor-post-status .components-select-control__input').val('rejected');
                    }
                }
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
                $result = wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'publish'
                ]);
            } else {
                $result = wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'rejected'
                ]);
                
                // Add default rejection reason if none exists
                $rejection_reason = get_post_meta($post_id, 'duvod_zamitnuti', true);
                if (empty($rejection_reason)) {
                    update_post_meta($post_id, 'duvod_zamitnuti', 'Rychle odmítnuto administrátorem');
                }
            }
            
            if (is_wp_error($result)) {
                error_log('[REALIZACE:ERROR] wp_update_post failed: ' . $result->get_error_message());
                wp_send_json_error(['message' => $result->get_error_message()]);
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

}