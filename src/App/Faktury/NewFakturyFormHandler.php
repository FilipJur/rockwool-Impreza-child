<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\FormHandlerBase;
use MistrFachman\Services\UserDetectionService;

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
     * Get the form title to match for this domain
     * TODO: Change to getFormId() when FormHandlerBase is refactored to use IDs
     */
    protected function getFormTitle(): string {
        return 'Přidat fakturu form';
    }

    /**
     * Get the post type slug for this domain
     */
    protected function getPostType(): string {
        return 'faktura';
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
        $invoice_value = (int) ($posted_data['hodnota_faktury'] ?? 0);
        return [
            'post_title'   => "Faktura - " . $invoice_value . " Kč - " . date('d.m.Y'),
            'post_content' => '', // Faktury don't need content description
        ];
    }

    /**
     * Save faktury-specific ACF/meta fields
     */
    protected function saveDomainFields(int $post_id, array $posted_data): void {
        // Save invoice value field
        if (isset($posted_data['hodnota_faktury'])) {
            $value = (int)$posted_data['hodnota_faktury'];
            $result = FakturaFieldService::setValue($post_id, $value);
            error_log('[FAKTURY:DEBUG] hodnota_faktury field update result: ' . ($result ? 'success' : 'failed') . ' | Value: ' . $value);
        }
        
        // TODO: Add additional domain fields as needed (invoice number, date, etc.)
        // These would typically be filled by admin during approval process
    }

    /**
     * Get the file field name from form data
     * Faktury uses single file upload (not gallery like Realizace)
     */
    protected function getGalleryFieldName(): string {
        return 'faktura_soubor';
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
}