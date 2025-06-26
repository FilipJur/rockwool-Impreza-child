<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\AdminControllerBase;
use MistrFachman\Base\AdminCardRendererBase;

/**
 * Faktury Admin Controller - Unified Admin Interface Controller
 *
 * Manages all admin-side faktury functionality with clear separation of concerns.
 * Mirrors Realizace AdminController pattern exactly.
 *
 * RESPONSIBILITIES:
 * - Status management coordination
 * - UI customizations (columns, forms, user profiles, users table integration)
 * - AJAX endpoints (quick actions, bulk operations)
 * - Asset management coordination
 * - Validation layer integration
 *
 * UI integration complete - admin columns, validation, and users table integration implemented
 * Follows Realizace architecture patterns with domain-specific customizations
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminController extends AdminControllerBase {

    public function __construct(
        private \MistrFachman\Services\StatusManager $status_manager,
        private AdminCardRendererBase $card_renderer,
        private \MistrFachman\Services\AdminAssetManager $asset_manager
    ) {
        // Smart status registration based on WordPress lifecycle
        if (did_action('init')) {
            $this->status_manager->register_custom_status();
        } else {
            add_action('init', [$this->status_manager, 'register_custom_status'], 1);
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

        // Gatekeeper validation hook - blocks invalid posts from being published
        add_filter('wp_insert_post_data', [$this, 'gatekeeper_validation'], 10, 2);
        
        // Display gatekeeper validation notices
        add_action('admin_notices', [$this, 'display_gatekeeper_validation_notices']);

        // AJAX hooks
        $this->init_ajax_hooks();
    }

    // ===========================================
    // UI MANAGEMENT SECTION
    // Responsibility: Admin interface customizations
    // ===========================================

    /**
     * Add custom columns to the admin list view
     */
    public function add_admin_columns(array $columns): array {
        // Reorder and add new columns
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['author'] = $columns['author'];
        $new_columns['invoice_value'] = __('Hodnota faktury', 'mistr-fachman');
        $new_columns['invoice_date'] = __('Datum faktury', 'mistr-fachman');
        $new_columns['status'] = __('Status', 'mistr-fachman'); // Using base class status column
        $new_columns['points'] = __('Body', 'mistr-fachman'); // Using base class points column
        $new_columns['date'] = $columns['date']; // Submission date
        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public function render_custom_columns(string $column, int $post_id): void {
        parent::render_custom_columns($column, $post_id); // Handles 'points' and 'status'

        switch ($column) {
            case 'invoice_value':
                $value = FakturaFieldService::getValue($post_id);
                echo esc_html(number_format($value, 0, ',', ' ')) . ' Kč';
                break;
            case 'invoice_date':
                $date_str = FakturaFieldService::getInvoiceDate($post_id);
                if ($date_str) {
                    $date = \DateTime::createFromFormat('Ymd', $date_str);
                    echo $date ? esc_html($date->format('d.m.Y')) : 'N/A';
                } else {
                    echo '—';
                }
                break;
        }
    }

    // ===========================================
    // AJAX SECTION
    // Responsibility: Admin AJAX endpoints
    // ===========================================

    /**
     * Initialize AJAX hooks
     */
    protected function init_ajax_hooks(): void {
        // Call parent to get proper dynamic hook registration with debugging
        parent::init_ajax_hooks();
    }

    /**
     * Handle quick action AJAX for faktury
     */
    public function handle_quick_action_ajax(): void {
        error_log('[FAKTURY:AJAX] Quick action called - START');
        error_log('[FAKTURY:AJAX] POST data: ' . json_encode($_POST));
        error_log('[FAKTURY:AJAX] Expected nonce action: mistr_fachman_' . $this->getPostType() . '_action');
        
        // Delegate to base class which has correct nonce verification
        parent::handle_quick_action_ajax();
    }

    /**
     * Handle AJAX approve action with validation
     */
    protected function process_approve_action(int $post_id): void {
        error_log("[FAKTURY:AJAX] Processing APPROVE action for post {$post_id}");

        $post = get_post($post_id);
        if (!$post) {
            throw new \Exception('Faktura nenalezena.');
        }

        // Get calculated points for this faktura
        $points_to_award = $this->getCalculatedPoints($post_id);
        
        error_log("[FAKTURY:AJAX] Calculated points for faktura {$post_id}: {$points_to_award}");
        
        // Save the calculated points for auditing
        FakturaFieldService::setPoints($post_id, $points_to_award);
        
        // Clear rejection reason
        FakturaFieldService::setRejectionReason($post_id, '');
        
        // Publish post
        $result = wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
        
        if (is_wp_error($result)) {
            throw new \Exception('Failed to update post status: ' . $result->get_error_message());
        }
        
        // Award points directly using PointsHandler public method
        $user_id = (int)$post->post_author;
        if ($user_id) {
            $points_handler = new PointsHandler();
            $success = $points_handler->award_points($post_id, $user_id, $points_to_award);
            
            if ($success) {
                error_log("[FAKTURY:AJAX] Successfully awarded {$points_to_award} points for post {$post_id} via direct method");
            } else {
                error_log("[FAKTURY:AJAX] Failed to award points for post {$post_id}");
            }
        }
    }

    /**
     * Run pre-publish validation (for Post Editor context)
     * Validates monthly limits, date requirements, and invoice number uniqueness
     */
    protected function run_pre_publish_validation(\WP_Post $post): array {
        $validator = new ValidationService($post);
        
        if (!$validator->isValid()) {
            return ['success' => false, 'message' => $validator->getValidationMessage()];
        }

        return ['success' => true];
    }

    /**
     * Gatekeeper validation - blocks invalid posts from being published
     * Runs before the post is saved to database
     */
    public function gatekeeper_validation(array $data, array $postarr): array {
        // Only run on our specific post type and when trying to publish
        if ($data['post_type'] !== $this->getPostType() || $data['post_status'] !== 'publish') {
            return $data;
        }

        // Don't run on new posts being created, only on updates
        if (empty($postarr['ID'])) {
            return $data;
        }

        $post = get_post($postarr['ID']);
        if (!$post) {
            return $data;
        }

        // Run our validation logic
        $validator = new ValidationService($post);
        $is_valid = $validator->isValid();

        if (!$is_valid) {
            // Validation FAILED. Block the status change.
            $data['post_status'] = $post->post_status; // Revert to original status

            // Set a transient to show the admin a persistent error notice.
            set_transient(
                'domain_validation_error_' . get_current_user_id(),
                [
                    'message' => $validator->getValidationMessage(),
                    'post_title' => $post->post_title
                ],
                30 // Expires in 30 seconds
            );
        }

        return $data;
    }

    /**
     * Display gatekeeper validation error notices
     */
    public function display_gatekeeper_validation_notices(): void {
        $error_data = get_transient('domain_validation_error_' . get_current_user_id());
        
        if ($error_data && is_array($error_data)) {
            delete_transient('domain_validation_error_' . get_current_user_id());
            
            printf(
                '<div class="notice notice-error is-dismissible"><p><strong>Publikování faktury "%s" bylo zablokováno:</strong> %s</p></div>',
                esc_html($error_data['post_title']),
                esc_html($error_data['message'])
            );
        }
    }

    // ===========================================
    // ABSTRACT METHOD IMPLEMENTATIONS
    // Required by AdminControllerBase
    // ===========================================

    /**
     * Get the post type slug for this domain (English, for internal logic)
     */
    protected function getPostType(): string {
        return 'invoice';
    }

    /**
     * Get the WordPress post type slug (Czech, for database/WP operations)
     */
    protected function getWordPressPostType(): string {
        return 'faktura';
    }


    /**
     * Get the display name for this domain
     */
    protected function getDomainDisplayName(): string {
        return 'Faktura';
    }

    /**
     * Get the calculated points for faktury (dynamic calculation)
     * Core business requirement: floor(invoice_value / 10) - 10 CZK = 1 bod
     */
    protected function getCalculatedPoints(int $post_id = 0): int {
        if ($post_id === 0) {
            return 0; // No post ID provided
        }
        
        $invoice_value = FakturaFieldService::getValue($post_id);
        if ($invoice_value <= 0) {
            return 0; // No valid invoice value
        }
        
        // Core business logic: floor(value / 10) - 10 CZK = 1 bod
        return (int) floor($invoice_value / 10);
    }

    /**
     * Get the points field selector
     */
    protected function getPointsFieldSelector(): string {
        return FakturaFieldService::getPointsFieldSelector();
    }

    /**
     * Get the rejection reason field selector
     */
    protected function getRejectionReasonFieldSelector(): string {
        return FakturaFieldService::getRejectionReasonFieldSelector();
    }

    /**
     * Get the gallery field selector for this domain
     * For faktury, we use file field instead of gallery
     */
    protected function getGalleryFieldSelector(): string {
        return FakturaFieldService::getFileFieldSelector();
    }

    /**
     * Render faktury-specific user profile card
     */
    protected function render_user_profile_card(\WP_User $user): void {
        echo '<div class="invoice-management-modern">';
        $this->card_renderer->render_consolidated_dashboard($user);
        echo '</div>';
    }

    /**
     * Process reject action with comprehensive status validation
     */
    protected function process_reject_action(int $post_id, \WP_Post $post, string $rejection_reason = ''): void {
        error_log("[FAKTURY:AJAX] Processing REJECT action for post {$post_id}");
        error_log("[FAKTURY:AJAX] Current post status before reject: {$post->post_status}");

        // Check if rejected status is available
        if (!$this->status_manager->is_custom_status_available()) {
            error_log("[FAKTURY:AJAX] Rejected status not available - attempting emergency registration");

            if (!$this->status_manager->emergency_register_custom_status()) {
                throw new \Exception('Chyba: Status "odmítnuto" není dostupný. Kontaktujte administrátora.');
            }
        }

        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'rejected'
        ]);

        if (is_wp_error($result)) {
            throw new \Exception('Chyba při změně statusu faktury: ' . $result->get_error_message());
        }

        // Save rejection reason if provided
        if (!empty($rejection_reason)) {
            FakturaFieldService::setRejectionReason($post_id, $rejection_reason);
            error_log("[FAKTURY:AJAX] Rejection reason saved: {$rejection_reason}");
        }

        error_log("[FAKTURY:AJAX] Post {$post_id} successfully rejected");
    }

    // ===========================================
    // WORDPRESS HOOK DELEGATION METHODS
    // Required for proper callback resolution
    // ===========================================

    /**
     * Handle status transitions for faktury posts
     * Domain-specific implementation following Realizace pattern
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

        // Handle point operations based on status changes
        if ($new_status === 'publish' && $old_status !== 'publish') {
            // Award points when publishing
            $points_handler = new PointsHandler();
            $points_to_award = $this->getCalculatedPoints($post->ID);
            
            if ($points_to_award > 0) {
                // Save calculated points
                FakturaFieldService::setPoints($post->ID, $points_to_award);
                
                // Award points
                $points_handler->award_points($post->ID, (int)$post->post_author, $points_to_award);
                error_log("[{$domain_debug}:UI] Awarded {$points_to_award} points for post #{$post->ID}");
            }
        } elseif ($old_status === 'publish' && $new_status !== 'publish') {
            // Revoke points when unpublishing
            $points_handler = new PointsHandler();
            // Note: revoke_points is protected in PointsHandlerBase, called automatically via hooks
            error_log("[{$domain_debug}:UI] Points will be revoked for post #{$post->ID} (status: {$new_status})");
        }
    }

    /**
     * Handle pre-publish validation for faktury posts
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

    /**
     * Handle bulk approve AJAX action
     * Delegates to parent class implementation
     */
    public function handle_bulk_approve_ajax(): void {
        parent::handle_bulk_approve_ajax();
    }

    /**
     * Process bulk approve operation for faktury
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
        $points_handler = new PointsHandler();

        foreach ($pending_posts as $post_id) {
            $calculated_points = $this->getCalculatedPoints($post_id);
            FakturaFieldService::setPoints($post_id, $calculated_points);

            // Clear rejection reason
            FakturaFieldService::setRejectionReason($post_id, '');

            // Publish the post
            $result = wp_update_post([
                'ID' => $post_id,
                'post_status' => 'publish'
            ]);

            if (!is_wp_error($result)) {
                $success = $points_handler->award_points($post_id, $user_id, $calculated_points);
                if ($success) {
                    $approved_count++;
                    error_log("[FAKTURY:AJAX] Bulk approved post {$post_id} with {$calculated_points} points");
                }
            }
        }

        return $approved_count;
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
}