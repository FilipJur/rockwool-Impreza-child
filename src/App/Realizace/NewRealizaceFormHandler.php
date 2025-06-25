<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

use MistrFachman\Base\FormHandlerBase;
use MistrFachman\Services\UserDetectionService;

/**
 * Realizace Form Handler - Domain-Specific Implementation
 *
 * Extends base form handler with realizace-specific field mappings and ACF integration.
 * Focuses only on realizace-specific logic while delegating generic operations to base class.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class NewRealizaceFormHandler extends FormHandlerBase {

    public function __construct(
        UserDetectionService $user_detection_service,
        Manager $manager
    ) {
        parent::__construct($user_detection_service, $manager);
    }

    /**
     * Get the form title to match for this domain
     */
    protected function getFormTitle(): string {
        return 'Přidat realizaci';
    }

    /**
     * Get the post type slug for this domain
     */
    protected function getPostType(): string {
        return 'realization';
    }

    /**
     * Get the display name for this domain (for logging)
     */
    protected function getDomainDisplayName(): string {
        return 'Realizace';
    }

    /**
     * Map form fields to post arguments
     * Realizace-specific field mappings
     */
    protected function mapFormDataToPost(array $posted_data): array {
        return [
            'post_title'   => sanitize_text_field($posted_data['project-title'] ?? ''),
            'post_content' => sanitize_textarea_field($posted_data['popis_projektu'] ?? ''),
        ];
    }

    /**
     * Save realizace-specific ACF/meta fields
     */
    protected function saveDomainFields(int $post_id, array $posted_data): void {
        // Save area field
        if (isset($posted_data['area_sqm'])) {
            $area_value = (string)(int)$posted_data['area_sqm'];
            $result = RealizaceFieldService::setFieldValue(
                RealizaceFieldService::getAreaFieldSelector(), 
                $post_id, 
                $area_value
            );
            error_log('[REALIZACE:DEBUG] area_sqm field update result: ' . ($result ? 'success' : 'failed') . ' | Value: ' . $area_value);
        }
        
        // Save construction type field
        if (isset($posted_data['construction_type'])) {
            $construction_value = sanitize_textarea_field($posted_data['construction_type']);
            $result = RealizaceFieldService::setFieldValue(
                RealizaceFieldService::getConstructionTypeFieldSelector(), 
                $post_id, 
                $construction_value
            );
            error_log('[REALIZACE:DEBUG] construction_type field update result: ' . ($result ? 'success' : 'failed') . ' | Value: ' . substr($construction_value, 0, 50) . '...');
        }
        
        // Save materials field
        if (isset($posted_data['materials_used'])) {
            $materials_value = sanitize_textarea_field($posted_data['materials_used']);
            $result = RealizaceFieldService::setFieldValue(
                RealizaceFieldService::getMaterialsFieldSelector(), 
                $post_id, 
                $materials_value
            );
            error_log('[REALIZACE:DEBUG] materials_used field update result: ' . ($result ? 'success' : 'failed') . ' | Value: ' . substr($materials_value, 0, 50) . '...');
        }
    }

    /**
     * Get the gallery field name from form data
     */
    protected function getGalleryFieldName(): string {
        return 'realization_gallery';
    }

    /**
     * Save gallery data using realizace field service
     */
    protected function saveGalleryData(int $post_id, array $gallery_ids): bool {
        return RealizaceFieldService::setFieldValue(
            RealizaceFieldService::getGalleryFieldSelector(), 
            $post_id, 
            $gallery_ids
        );
    }
}