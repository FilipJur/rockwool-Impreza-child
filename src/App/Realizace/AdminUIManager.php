<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Admin UI Manager - Admin Interface Customization
 *
 * Handles all admin UI customizations including custom columns, form modifications,
 * JavaScript enhancements, and post save handling. Provides comprehensive
 * WordPress admin integration.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminUIManager {

    public function __construct(
        private AdminCardRenderer $card_renderer
    ) {}

    /**
     * Initialize admin UI hooks
     */
    public function init_hooks(): void {
        // Admin list view customization
        add_filter('manage_realizace_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_realizace_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        
        // Post editor form customization
        add_action('admin_footer-post.php', [$this, 'add_rejected_status_to_dropdown']);
        add_action('admin_footer-post-new.php', [$this, 'add_rejected_status_to_dropdown']);
        
        // Post save handling
        add_action('save_post_realizace', [$this, 'handle_realizace_save'], 5, 2);
        add_action('transition_post_status', [$this, 'handle_status_transition'], 10, 3);
        
        // User profile integration
        add_action('mistr_fachman_user_profile_cards', [$this, 'add_realizace_cards_to_user_profile'], 10, 2);
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
     * @param string $column Column name
     * @param int $post_id Post ID
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
     *
     * @param int $post_id Post ID
     */
    private function render_points_column(int $post_id): void {
        $points_assigned = function_exists('get_field') 
            ? get_field('pridelene_body', $post_id) 
            : get_post_meta($post_id, 'pridelene_body', true);
        
        $points_awarded = (int)get_post_meta($post_id, '_realizace_points_awarded', true);
        
        if ($points_assigned) {
            echo '<strong>' . esc_html($points_assigned) . '</strong>';
            if ($points_awarded && $points_awarded !== (int)$points_assigned) {
                echo '<br><small>Uděleno: ' . esc_html($points_awarded) . '</small>';
            }
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }

    /**
     * Render status column content
     *
     * @param int $post_id Post ID
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
     *
     * @param string $status Post status
     * @return array Display configuration
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

        error_log("[REALIZACE:UI] Processing save for post {$post_id}");
        error_log("[REALIZACE:UI] Post status from object: {$post->post_status}");
        
        // Check if this is a status change via admin form
        $requested_status = $_POST['post_status'] ?? $_POST['_status'] ?? null;
        error_log("[REALIZACE:UI] Requested status from POST: " . ($requested_status ?: 'none'));
        
        // If user requested rejected status, ensure it's preserved
        if ($requested_status === 'rejected') {
            error_log("[REALIZACE:UI] User requested rejected status - preserving it");
            
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
                error_log("[REALIZACE:UI] Failed to preserve rejected status: " . $result->get_error_message());
            } else {
                error_log("[REALIZACE:UI] Rejected status preserved for post {$post_id}");
            }
        }
        
        // Log final status
        $final_post = get_post($post_id);
        error_log("[REALIZACE:UI] Final post status: {$final_post->post_status}");
    }

    /**
     * Handle status transitions for logging and validation
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param \WP_Post $post Post object
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
            echo '<div class="realizace-management-modern">';
            echo '<div class="management-layout">';
            
            // Left sidebar - compact overview
            echo '<div class="management-sidebar">';
            $this->card_renderer->render_realizace_overview_card($user);
            echo '</div>';
            
            // Right main area - realizace grid
            echo '<div class="management-main">';
            $this->card_renderer->render_recent_realizace_card($user);
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
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