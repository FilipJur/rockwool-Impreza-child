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
     * Get the form ID to match for this domain
     */
    protected function getFormId(): int {
        // ID for "Přidat realizaci" form
        return 320;
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
            \MistrFachman\Services\DebugLogger::log('[REALIZACE] area_sqm field update', [
                'result' => $result ? 'success' : 'failed',
                'value' => $area_value
            ]);
        }
        
        // Save NEW construction types field (taxonomy IDs) - handle both field name patterns
        if (isset($posted_data['your-construction-types']) && is_array($posted_data['your-construction-types'])) {
            $construction_ids = array_filter(array_map('intval', $posted_data['your-construction-types']));
            $result = RealizaceFieldService::setFieldValue(
                RealizaceFieldService::getConstructionTypeFieldSelector(), 
                $post_id, 
                $construction_ids
            );
            \MistrFachman\Services\DebugLogger::log('[REALIZACE] NEW construction-types field update', [
                'result' => $result ? 'success' : 'failed',
                'term_ids' => $construction_ids,
                'field_selector' => RealizaceFieldService::getConstructionTypeFieldSelector()
            ]);
        }
        // Also try the alternative field name pattern
        elseif (isset($posted_data['construction-types']) && is_array($posted_data['construction-types'])) {
            $construction_ids = array_filter(array_map('intval', $posted_data['construction-types']));
            $result = RealizaceFieldService::setFieldValue(
                RealizaceFieldService::getConstructionTypeFieldSelector(), 
                $post_id, 
                $construction_ids
            );
            \MistrFachman\Services\DebugLogger::log('[REALIZACE] ALT construction-types field update', [
                'result' => $result ? 'success' : 'failed',
                'term_ids' => $construction_ids,
                'field_selector' => RealizaceFieldService::getConstructionTypeFieldSelector()
            ]);
        }
        // Fallback to old construction type field (legacy support)
        elseif (isset($posted_data['construction_type'])) {
            $construction_value = sanitize_textarea_field($posted_data['construction_type']);
            $result = RealizaceFieldService::setFieldValue(
                RealizaceFieldService::getConstructionTypeFieldSelector(), 
                $post_id, 
                $construction_value
            );
            \MistrFachman\Services\DebugLogger::log('[REALIZACE] OLD construction_type field update', [
                'result' => $result ? 'success' : 'failed', 
                'value' => substr($construction_value, 0, 50) . '...'
            ]);
        }
        
        // Save NEW materials field (taxonomy IDs) - handle both field name patterns  
        if (isset($posted_data['your-materials']) && is_array($posted_data['your-materials'])) {
            $material_ids = array_filter(array_map('intval', $posted_data['your-materials']));
            $result = RealizaceFieldService::setFieldValue(
                RealizaceFieldService::getMaterialsFieldSelector(), 
                $post_id, 
                $material_ids
            );
            \MistrFachman\Services\DebugLogger::log('[REALIZACE] NEW materials field update', [
                'result' => $result ? 'success' : 'failed',
                'term_ids' => $material_ids,
                'field_selector' => RealizaceFieldService::getMaterialsFieldSelector()
            ]);
        }
        // Also try the alternative field name pattern
        elseif (isset($posted_data['materials']) && is_array($posted_data['materials'])) {
            $material_ids = array_filter(array_map('intval', $posted_data['materials']));
            $result = RealizaceFieldService::setFieldValue(
                RealizaceFieldService::getMaterialsFieldSelector(), 
                $post_id, 
                $material_ids
            );
            \MistrFachman\Services\DebugLogger::log('[REALIZACE] ALT materials field update', [
                'result' => $result ? 'success' : 'failed',
                'term_ids' => $material_ids,
                'field_selector' => RealizaceFieldService::getMaterialsFieldSelector()
            ]);
        }
        // Fallback to old materials field (legacy support)
        elseif (isset($posted_data['materials_used'])) {
            $materials_value = sanitize_textarea_field($posted_data['materials_used']);
            $result = RealizaceFieldService::setFieldValue(
                RealizaceFieldService::getMaterialsFieldSelector(), 
                $post_id, 
                $materials_value
            );
            \MistrFachman\Services\DebugLogger::log('[REALIZACE] OLD materials_used field update', [
                'result' => $result ? 'success' : 'failed',
                'value' => substr($materials_value, 0, 50) . '...'
            ]);
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

    /**
     * Populate initial post meta data immediately after post creation
     * For Realizace: Set fixed 2500 points
     */
    protected function populate_initial_post_meta(int $post_id, array $posted_data): void {
        // $posted_data not used for Realizace - fixed points only
        $points_handler = new PointsHandler();
        $calculated_points = $points_handler->getCalculatedPoints(); // Returns 2500
        RealizaceFieldService::setPoints($post_id, $calculated_points);
    }
}