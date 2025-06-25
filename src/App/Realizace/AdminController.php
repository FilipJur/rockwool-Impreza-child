<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

use MistrFachman\Base\AdminControllerBase;
use MistrFachman\Base\AdminCardRendererBase;

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

class AdminController extends AdminControllerBase {
    // Implements getCalculatedPoints() method - replaces legacy getDefaultPoints()

    public function __construct(
        private StatusManager $status_manager,
        private AdminCardRendererBase $card_renderer,
        private AdminAssetManager $asset_manager
    ) {

        // Smart status registration based on WordPress lifecycle
        if (did_action('init')) {
            $this->status_manager->register_rejected_status();
        } else {
            add_action('init', [$this->status_manager, 'register_rejected_status'], 1);
        }
    }

    /**
     * Initialize all admin hooks with clear responsibility sections
     */
    public function init_hooks(): void {
        // Initialize base class hooks
        $this->init_common_hooks();

        // Status management hooks
        $this->status_manager->init_hooks();

        // Asset management hooks - asset manager handles its own conditional logic
        add_action('admin_enqueue_scripts', [$this->asset_manager, 'enqueue_admin_assets']);
    }

    // ===========================================
    // UI MANAGEMENT SECTION
    // Responsibility: Admin interface customizations
    // ===========================================

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
    protected function render_points_column(int $post_id): void {
        // SINGLE SOURCE OF TRUTH: Use base class method
        $points_assigned = $this->get_current_points($post_id);
        $points_awarded = (int)get_post_meta($post_id, '_realizace_points_awarded', true);

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
     * Handle status transitions for logging and validation
     */
    public function handle_status_transition(string $new_status, string $old_status, \WP_Post $post): void {
        if ($post->post_type !== $this->getPostType()) {
            return;
        }

        $domain_debug = strtoupper($this->getPostType());
        error_log("[{$domain_debug}:UI] Status transition: {$old_status} → {$new_status} for post #{$post->ID}");

        // Validate rejection reason when moving to rejected status
        if ($new_status === 'rejected' && $old_status !== 'rejected') {
            $rejection_reason = function_exists('get_field')
                ? get_field($this->getRejectionReasonFieldSelector(), $post->ID)
                : get_post_meta($post->ID, $this->getRejectionReasonFieldSelector(), true);

            if (empty($rejection_reason)) {
                error_log("[{$domain_debug}:UI] Warning: Post #{$post->ID} rejected without reason");
            }
        }

        // Clear any cached data when status changes
        wp_cache_delete("{$this->getPostType()}_status_{$post->ID}", 'posts');
    }

    /**
     * Add realizace management cards to user profile
     */
    public function add_cards_to_user_profile(\WP_User $user, bool $is_pending): void {
        // Only show cards for users who can submit posts of this type
        // (pending users and full members)
        if (!$is_pending && !in_array('full_member', $user->roles)) {
            return;
        }

        // Check if user has any posts of this type
        $has_posts = $this->user_has_posts($user->ID);

        // Show cards if user has posts or is eligible to submit them
        if ($has_posts || $is_pending || in_array('full_member', $user->roles)) {
            $this->render_user_profile_card($user);
        }
    }

    // ===========================================
    // AJAX MANAGEMENT SECTION
    // Responsibility: AJAX endpoint processing
    // ===========================================

    /**
     * Initialize AJAX hooks
     */
    protected function init_ajax_hooks(): void {
        // Call parent to get proper dynamic hook registration with debugging
        parent::init_ajax_hooks();
    }


    /**
     * Process approve action - AJAX pathway with direct point award
     * Saves data, publishes post, and directly awards points (bypassing hooks)
     */
    protected function process_approve_action(int $post_id): void {
        error_log("[REALIZACE:AJAX] Processing APPROVE action for post {$post_id}");

        // 1. Determine the points value from the AJAX UI
        $points_to_award = (isset($_POST['points']) && is_numeric($_POST['points']))
            ? (int)$_POST['points']
            : $this->get_current_points($post_id);

        if ($points_to_award <= 0) {
            $points_to_award = $this->getCalculatedPoints($post_id);
        }

        error_log("[REALIZACE:AJAX] Setting points value: {$points_to_award} for post {$post_id}");

        // 2. SAVE this value
        $this->set_points($post_id, $points_to_award);

        // 3. Clear rejection reason
        RealizaceFieldService::setRejectionReason($post_id, '');

        // 4. Publish the post
        $result = wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);

        if (is_wp_error($result)) {
            throw new \Exception('Failed to update post status: ' . $result->get_error_message());
        }

        // 5. AJAX pathway: Award points directly using PointsHandler public method
        $user_id = (int)get_post_field('post_author', $post_id);
        if ($user_id) {
            // Get PointsHandler instance to award points directly
            $points_handler = new \MistrFachman\Realizace\PointsHandler();
            $success = $points_handler->award_points($post_id, $user_id, $points_to_award);

            if ($success) {
                error_log("[REALIZACE:AJAX] Successfully awarded {$points_to_award} points for post {$post_id} via direct method");
            } else {
                error_log("[REALIZACE:AJAX] Failed to award points for post {$post_id}");
            }
        }
    }

    /**
     * Process reject action with comprehensive status validation
     */
    protected function process_reject_action(int $post_id, \WP_Post $post, string $rejection_reason = ''): void {
        error_log("[REALIZACE:AJAX] Processing REJECT action for post {$post_id}");
        error_log("[REALIZACE:AJAX] Current post status before reject: {$post->post_status}");

        // Check if rejected status is available
        if (!$this->status_manager->is_rejected_status_available()) {
            error_log("[REALIZACE:AJAX] Rejected status not available - attempting emergency registration");

            if (!$this->status_manager->emergency_register_rejected_status()) {
                throw new \Exception('Chyba: Status "odmítnuto" není dostupný. Kontaktujte administrátora.');
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
            RealizaceFieldService::setRejectionReason($post_id, $rejection_reason);
            error_log("[REALIZACE:AJAX] Set user-provided rejection reason: {$rejection_reason}");
        } else {
            // Only set default if no existing reason and no user input
            $existing_reason = RealizaceFieldService::getRejectionReason($post_id);
            if (empty($existing_reason)) {
                RealizaceFieldService::setRejectionReason($post_id, 'Rychle odmítnuto administrátorem');
                error_log("[REALIZACE:AJAX] Added default rejection reason");
            }
        }

        // Check for wp_update_post errors
        if (is_wp_error($result)) {
            throw new \Exception('Failed to update post status: ' . $result->get_error_message());
        }
    }


    /**
     * Handle bulk approve AJAX action with improved points collection
     */
    public function handle_bulk_approve_ajax(): void {
        error_log('[REALIZACE:AJAX] Bulk approve called - delegating to base class');
        
        // Delegate to base class which has correct nonce verification
        parent::handle_bulk_approve_ajax();
    }

    /**
     * Process bulk approve operation - AJAX pathway with direct point awards
     * Business Rule: Bulk approve always uses the fixed default value (2500)
     */
    protected function process_bulk_approve(int $user_id): int {
        $pending_posts = get_posts([
            'post_type' => $this->getPostType(),
            'author' => $user_id,
            'post_status' => 'pending',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        error_log("[REALIZACE:AJAX] Found " . count($pending_posts) . " pending posts for user {$user_id}");

        $approved_count = 0;
        $points_handler = new \MistrFachman\Realizace\PointsHandler();

        foreach ($pending_posts as $post_id) {
            // Business Rule: Bulk approve always uses the calculated value.
            $calculated_points = $this->getCalculatedPoints($post_id);
            $this->set_points($post_id, $calculated_points);

            // Clear rejection reason
            RealizaceFieldService::setRejectionReason($post_id, '');

            // Publish the post
            $result = wp_update_post([
                'ID' => $post_id,
                'post_status' => 'publish'
            ]);

            if (!is_wp_error($result)) {
                // AJAX pathway: Award points directly
                $success = $points_handler->award_points($post_id, $user_id, $calculated_points);

                if ($success) {
                    $approved_count++;
                    error_log("[REALIZACE:AJAX] Bulk approved post {$post_id} with {$calculated_points} points via direct method");
                } else {
                    error_log("[REALIZACE:AJAX] Failed to award points for bulk approved post {$post_id}");
                }
            } else {
                error_log("[REALIZACE:AJAX] Failed to bulk approve post {$post_id}: " . $result->get_error_message());
            }
        }

        return $approved_count;
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

    // ===========================================
    // ABSTRACT METHOD IMPLEMENTATIONS
    // Required by AdminControllerBase
    // ===========================================

    /**
     * Get the post type slug for this domain
     */
    protected function getPostType(): string {
        return 'realizace';
    }

    /**
     * Get the domain key for DomainRegistry usage
     */
    protected function getDomainKey(): string {
        return 'realizace';
    }

    /**
     * Get the display name for this domain
     */
    protected function getDomainDisplayName(): string {
        return 'Realizace';
    }

    /**
     * Get the calculated points for realizace (fixed 2500)
     */
    protected function getCalculatedPoints(int $post_id = 0): int {
        // Fixed value for Realizace domain - $post_id parameter not used
        return 2500;
    }

    /**
     * Get the points field selector
     * Delegates to centralized field service
     */
    protected function getPointsFieldSelector(): string {
        return RealizaceFieldService::getPointsFieldSelector();
    }

    /**
     * Get the rejection reason field selector
     * Delegates to centralized field service
     */
    protected function getRejectionReasonFieldSelector(): string {
        return RealizaceFieldService::getRejectionReasonFieldSelector();
    }

    /**
     * Get the gallery field selector for realizace
     * Delegates to centralized field service
     */
    protected function getGalleryFieldSelector(): string {
        return RealizaceFieldService::getGalleryFieldSelector();
    }

    /**
     * Get the area/size field selector for realizace
     * Delegates to centralized field service
     */
    protected function getAreaFieldSelector(): string {
        return RealizaceFieldService::getAreaFieldSelector();
    }

    /**
     * Get the construction type field selector for realizace
     * Delegates to centralized field service
     */
    protected function getConstructionTypeFieldSelector(): string {
        return RealizaceFieldService::getConstructionTypeFieldSelector();
    }

    /**
     * Get the materials field selector for realizace
     * Delegates to centralized field service
     */
    protected function getMaterialsFieldSelector(): string {
        return RealizaceFieldService::getMaterialsFieldSelector();
    }

    /**
     * {@inheritdoc}
     * Runs pre-publish validation for the Realizace domain.
     * Currently, there are no specific pre-publish rules for Realizace.
     */
    protected function run_pre_publish_validation(\WP_Post $post): array {
        // No specific validation rules for Realizace at this time
        // $post parameter available for future validation logic
        // Always return success to allow publishing
        return ['success' => true];
    }

    /**
     * Render realizace-specific user profile card
     */
    protected function render_user_profile_card(\WP_User $user): void {
        echo '<div class="realizace-management-modern">';
        $this->card_renderer->render_consolidated_dashboard($user);
        echo '</div>';
    }

    /**
     * Check if user has posts of this domain type
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

    // ===========================================
    // WORDPRESS HOOK DELEGATION METHODS
    // Required for proper callback resolution
    // ===========================================

    /**
     * Handle pre-publish validation for realizace posts
     * Delegates to parent class implementation
     */
    public function handle_pre_publish_validation(string $new_status, string $old_status, \WP_Post $post): void {
        parent::handle_pre_publish_validation($new_status, $old_status, $post);
    }

    /**
     * Display validation admin notices
     * Delegates to parent class implementation
     */
    public function display_validation_admin_notices(): void {
        parent::display_validation_admin_notices();
    }

    /**
     * Add pending column to users table
     * Delegates to parent class implementation
     */
    public function add_pending_column_to_users_table(array $columns): array {
        return parent::add_pending_column_to_users_table($columns);
    }

    /**
     * Render pending column content for users table
     * Delegates to parent class implementation
     */
    public function render_pending_column_for_users_table(string $output, string $column_name, int $user_id): string {
        return parent::render_pending_column_for_users_table($output, $column_name, $user_id);
    }

}

