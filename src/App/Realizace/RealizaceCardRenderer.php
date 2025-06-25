<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

use MistrFachman\Base\AdminCardRendererBase;

/**
 * Realizace Card Renderer - Domain-Specific Data Provider
 *
 * Lean implementation providing only realizace-specific data and field access.
 * All HTML rendering delegated to reusable templates.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RealizaceCardRenderer extends AdminCardRendererBase {

    private ?AdminController $admin_controller = null;

    /**
     * Set the admin controller for field access
     */
    public function setAdminController(AdminController $admin_controller): void {
        $this->admin_controller = $admin_controller;
    }

    /**
     * Get the post type slug for this domain
     */
    protected function getPostType(): string {
        return 'realizace';
    }

    /**
     * Get the display name for this domain
     */
    protected function getDomainDisplayName(): string {
        return 'realizací';
    }

    /**
     * Get points value for a realizace post using centralized field access
     */
    protected function getPoints(int $post_id): int {
        // Use admin controller's abstract method if available, fallback to field service
        if ($this->admin_controller) {
            return $this->admin_controller->get_current_points($post_id);
        }
        
        // Use centralized field service
        return RealizaceFieldService::getPoints($post_id);
    }

    /**
     * Get rejection reason for a realizace post
     */
    protected function getRejectionReason(int $post_id): string {
        return RealizaceFieldService::getRejectionReason($post_id);
    }

    /**
     * Get gallery images for a realizace post
     */
    protected function getGalleryImages(int $post_id): array {
        return RealizaceFieldService::getGallery($post_id);
    }

    /**
     * Get realizace-specific field data
     */
    protected function getDomainFieldData(int $post_id): array {
        return [
            'area' => RealizaceFieldService::getArea($post_id),
            'construction_type' => RealizaceFieldService::getConstructionType($post_id),
            'materials' => RealizaceFieldService::getMaterials($post_id),
        ];
    }

    /**
     * Render realizace-specific detail items (delegates to template)
     */
    protected function renderDomainDetails(array $field_data): void {
        $this->load_template('domain-details/realizace-details.php', compact('field_data'));
    }

    /**
     * Legacy method for backwards compatibility
     * Delegates to base class consolidated dashboard
     */
    public function render_consolidated_realizace_dashboard(\WP_User $user): void {
        $this->render_consolidated_dashboard($user);
    }
}