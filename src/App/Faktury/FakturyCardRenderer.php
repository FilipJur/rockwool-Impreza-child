<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\AdminCardRendererBase;
use MistrFachman\Services\DomainConfigurationService;

/**
 * Faktury Card Renderer - Domain-Specific Data Provider
 *
 * Lean implementation providing only faktury-specific data and field access.
 * All HTML rendering delegated to reusable templates.
 * Mirrors RealizaceCardRenderer pattern exactly.
 *
 * TODO: Implement full card rendering and template integration
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FakturyCardRenderer extends AdminCardRendererBase {

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
        return 'invoice';
    }

    /**
     * Get the display name for this domain
     */
    protected function getDomainDisplayName(): string {
        return 'faktur';
    }

    /**
     * Get points value for a faktura post using centralized field access
     * Required by AdminCardRendererBase
     */
    protected function getPoints(int $post_id): int {
        // Use admin controller's get_current_points method if available, fallback to field service
        if ($this->admin_controller) {
            return $this->admin_controller->get_current_points($post_id);
        }
        
        // Use centralized field service
        return FakturaFieldService::getPoints($post_id);
    }

    /**
     * Get rejection reason for a faktura post using centralized field access
     * Required by AdminCardRendererBase
     */
    protected function getRejectionReason(int $post_id): string {
        return FakturaFieldService::getRejectionReason($post_id);
    }

    /**
     * Get gallery images for a faktura post
     * Required by AdminCardRendererBase
     * For faktury, we use single file instead of gallery
     */
    protected function getGalleryImages(int $post_id): array {
        $file = FakturaFieldService::getFile($post_id);
        // Convert single file to array format for consistency
        return $file ? [$file] : [];
    }

    /**
     * Get faktury-specific field data
     * Required by AdminCardRendererBase
     */
    protected function getDomainFieldData(int $post_id): array {
        return [
            DomainConfigurationService::getColumnKey('invoice', 'invoice_value') => FakturaFieldService::getValue($post_id),
            DomainConfigurationService::getColumnKey('invoice', 'invoice_file') => FakturaFieldService::getFile($post_id),
            DomainConfigurationService::getColumnKey('invoice', 'invoice_number') => FakturaFieldService::getInvoiceNumber($post_id),
            DomainConfigurationService::getColumnKey('invoice', 'invoice_date') => FakturaFieldService::getInvoiceDate($post_id),
        ];
    }

    /**
     * Render faktury-specific detail items (delegates to template)
     * Required by AdminCardRendererBase
     * TODO: Create faktury-details.php template
     */
    protected function renderDomainDetails(array $field_data): void {
        // TODO: Create template at templates/admin-cards/domain-details/faktura-details.php
        $this->load_template('domain-details/faktura-details.php', compact('field_data'));
    }

    /**
     * Legacy method for backwards compatibility
     * Delegates to base class consolidated dashboard
     */
    public function render_consolidated_faktury_dashboard(\WP_User $user): void {
        $this->render_consolidated_dashboard($user);
    }
}