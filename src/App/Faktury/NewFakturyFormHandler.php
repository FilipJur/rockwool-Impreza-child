<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\FormHandlerBase;
use MistrFachman\Services\UserDetectionService;
use MistrFachman\Services\DomainConfigurationService;

/**
 * Faktury Form Handler - Domain-Specific Implementation
 *
 * Extends base form handler with faktury-specific field mappings and ACF integration.
 * Focuses only on faktury-specific logic while delegating generic operations to base class.
 * Mirrors NewRealizaceFormHandler pattern exactly.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class NewFakturyFormHandler extends FormHandlerBase {

    public function __construct(
        UserDetectionService $user_detection_service,
        Manager $manager
    ) {
        parent::__construct($user_detection_service, $manager);
    }

    /**
     * Get the form ID to match for this domain
     */
    protected function getFormId(): int {
        // ID for "Přidat fakturu form" form
        return 554;
    }

    /**
     * Get the post type slug for this domain
     */
    protected function getPostType(): string {
        return 'invoice';
    }

    /**
     * Get the display name for this domain (for logging)
     */
    protected function getDomainDisplayName(): string {
        return 'Faktura';
    }

    /**
     * Map form fields to post arguments
     * Faktury-specific field mappings
     */
    protected function mapFormDataToPost(array $posted_data): array {
        $invoice_value = (int) ($posted_data['invoice_value'] ?? 0);
        return [
            'post_title'   => "Faktura - " . $invoice_value . " Kč - " . date('d.m.Y'),
            'post_content' => '', // Faktury don't need content description
        ];
    }

    /**
     * Save faktury-specific ACF/meta fields
     * FormHandler only saves raw user input - calculations happen in ACF hooks
     */
    protected function saveDomainFields(int $post_id, array $posted_data): void {
        // Save invoice value field (raw user input only)
        if (isset($posted_data['invoice_value'])) {
            $value = (int)$posted_data['invoice_value'];
            $result = FakturaFieldService::setValue($post_id, $value);
            error_log('[FAKTURY:DEBUG] invoice_value field update result: ' . ($result ? 'success' : 'failed') . ' | Value: ' . $value);
        }
        
        // Points calculation is handled by PointsHandler ACF hook
        // This ensures clean separation: FormHandler saves data, ACF hook does calculations
        
        // TODO: Add additional domain fields as needed (invoice number, date, etc.)
        // These would typically be filled by admin during approval process
    }

    /**
     * Get the file field name from form data
     * Faktury uses single file upload (not gallery like Realizace)
     */
    protected function getGalleryFieldName(): string {
        return DomainConfigurationService::getFieldName('invoice', 'file');
    }

    /**
     * Save file data using faktura field service
     * For faktury, we save a single file (not an array like galleries)
     */
    protected function saveGalleryData(int $post_id, array $file_ids): bool {
        // For a single file, just save the first ID
        if (!empty($file_ids)) {
            return FakturaFieldService::setFile($post_id, $file_ids[0]);
        }
        return false;
    }

    /**
     * Populate initial post meta data immediately after post creation
     * For Faktury: Calculate dynamic points based on invoice value (value / 30)
     */
    protected function populate_initial_post_meta(int $post_id, array $posted_data): void {
        // $posted_data not used - invoice value already saved in saveDomainFields step
        // We can now safely calculate and save the points
        $points_handler = new PointsHandler();
        $calculated_points = $points_handler->getCalculatedPoints($post_id);
        FakturaFieldService::setPoints($post_id, $calculated_points);
    }
}