<?php

declare(strict_types=1);

namespace MistrFachman\Base;

use MistrFachman\Services\ProjectStatusService;

/**
 * Base Admin Card Renderer - Lean Data Preparation Class
 *
 * Provides data preparation for admin dashboard cards with template-based rendering.
 * Separates business logic from presentation - no HTML generation in this class.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class AdminCardRendererBase {

    /**
     * Get the post type slug for this domain
     */
    abstract protected function getPostType(): string;

    /**
     * Get the display name for this domain
     */
    abstract protected function getDomainDisplayName(): string;

    /**
     * Get domain-specific field data for a post
     */
    abstract protected function getDomainFieldData(int $post_id): array;

    /**
     * Render domain-specific detail items (delegates to template)
     */
    abstract protected function renderDomainDetails(array $field_data): void;

    /**
     * Get points value for a post (domain-specific implementation)
     */
    abstract protected function getPoints(int $post_id): int;

    /**
     * Get rejection reason for a post (domain-specific implementation)
     */
    abstract protected function getRejectionReason(int $post_id): string;

    /**
     * Get gallery images for a post (domain-specific implementation)
     */
    abstract protected function getGalleryImages(int $post_id): array;

    /**
     * Render consolidated dashboard for a user
     * LEAN: Only prepares data and loads main template
     *
     * @param \WP_User $user User object
     */
    public function render_consolidated_dashboard(\WP_User $user): void {
        // 1. Prepare all the data
        $data = [
            'stats' => $this->get_user_stats($user->ID),
            'posts_by_status' => $this->get_posts_grouped_by_status($user->ID),
            'domain_name' => $this->getDomainDisplayName(),
            'post_type' => $this->getPostType(),
            'renderer' => $this, // Pass renderer instance for helper methods
            'user' => $user
        ];

        // 2. Load the main wrapper template and pass the data
        $this->load_template('dashboard-wrapper.php', $data);
    }

    /**
     * Template loading helper with security checks
     * 
     * @param string $template_name Template file name (e.g., 'dashboard-wrapper.php')
     * @param array $data Data to extract as variables for the template
     */
    public function load_template(string $template_name, array $data = []): void {
        $template_path = get_stylesheet_directory() . '/templates/admin-cards/' . $template_name;
        
        if (file_exists($template_path)) {
            // Extract data as variables for the template
            extract($data);
            // Include the template
            include $template_path;
        } else {
            error_log("[TEMPLATE:ERROR] Template not found: {$template_path}");
        }
    }

    /**
     * Get user statistics
     *
     * @param int $user_id User ID
     * @return array Statistics
     */
    protected function get_user_stats(int $user_id): array {
        global $wpdb;

        $post_type = $this->getPostType();
        $meta_key = "_{$post_type}_points_awarded";

        // Build dynamic status list for SQL query
        $valid_statuses = ProjectStatusService::getAllValidStatuses();
        $status_placeholders = implode(',', array_fill(0, count($valid_statuses), '%s'));
        
        $stats = $wpdb->get_results($wpdb->prepare("
            SELECT
                post_status,
                COUNT(*) as count,
                COALESCE(SUM(CAST(pm.meta_value AS SIGNED)), 0) as total_points
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = %s)
            WHERE p.post_type = %s
            AND p.post_author = %d
            AND p.post_status IN ($status_placeholders)
            GROUP BY post_status
        ", array_merge([$meta_key, $post_type, $user_id], $valid_statuses)), ARRAY_A);

        $result = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'total_points' => 0
        ];

        foreach ($stats as $stat) {
            $result['total'] += (int)$stat['count'];

            switch ($stat['post_status']) {
                case 'pending':
                    $result['pending'] = (int)$stat['count'];
                    break;
                case 'publish':
                    $result['approved'] = (int)$stat['count'];
                    $result['total_points'] += (int)$stat['total_points'];
                    break;
                case 'rejected':
                    $result['rejected'] = (int)$stat['count'];
                    break;
            }
        }

        return $result;
    }

    /**
     * Get posts grouped by status for consolidated dashboard
     *
     * @param int $user_id User ID
     * @return array Posts grouped by status
     */
    protected function get_posts_grouped_by_status(int $user_id): array {
        $query = new \WP_Query([
            'post_type' => $this->getPostType(),
            'author' => $user_id,
            'post_status' => ProjectStatusService::getAllValidStatuses(),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => true
        ]);

        $grouped = [
            'pending' => [],
            'approved' => [],
            'rejected' => []
        ];

        foreach ($query->posts as $post) {
            switch ($post->post_status) {
                case 'pending':
                    $grouped['pending'][] = $post;
                    break;
                case 'publish':
                    $grouped['approved'][] = $post;
                    break;
                case 'rejected':
                    $grouped['rejected'][] = $post;
                    break;
            }
        }

        return $grouped;
    }

    /**
     * Get status configuration for styling (helper for templates)
     *
     * @param string $status Post status
     * @return array Configuration
     */
    public function get_status_config(string $status): array {
        // Get status label from ProjectStatusService
        $label = ProjectStatusService::getStatusLabel($status);
        
        // Map status to admin card CSS classes
        $class = match ($status) {
            'publish' => 'status-success',
            'pending' => 'status-warning',
            'rejected' => 'status-error',
            'draft' => 'status-draft',
            default => 'status-default'
        };
        
        return [
            'label' => $label,
            'class' => $class
        ];
    }
}