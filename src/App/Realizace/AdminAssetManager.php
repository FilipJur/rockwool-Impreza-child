<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

use MistrFachman\Base\AdminAssetManagerBase;

/**
 * Realizace Admin Asset Manager - Domain-Specific Asset Management
 *
 * Extends AdminAssetManagerBase to provide realizace-specific asset management.
 * Handles CSS, JavaScript, and localization for realizace admin interface.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminAssetManager extends AdminAssetManagerBase {

    /**
     * Get the domain-specific script handle prefix
     */
    protected function getScriptHandlePrefix(): string {
        return 'realizace';
    }

    /**
     * Get the domain-specific localization object name
     */
    protected function getLocalizationObjectName(): string {
        return 'mistrRealizaceAdmin';
    }

    /**
     * Get domain-specific admin screen IDs where assets should load
     */
    protected function getAdminScreenIds(): array {
        return ['user-edit', 'profile', 'users', 'post', 'realizace'];
    }





    /**
     * Get domain-specific localized data for scripts
     */
    protected function getDomainLocalizationData(): array {
        return [
            'field_names' => [
                'rejection_reason' => RealizaceFieldService::getRejectionReasonFieldSelector(),
                'points' => RealizaceFieldService::getPointsFieldSelector(),
                'gallery' => RealizaceFieldService::getGalleryFieldSelector(),
                'area' => RealizaceFieldService::getAreaFieldSelector(),
                'construction_type' => RealizaceFieldService::getConstructionTypeFieldSelector(),
                'materials' => RealizaceFieldService::getMaterialsFieldSelector()
            ],
            'default_values' => [
                'points' => 2500
            ],
            'messages' => array_merge($this->getDefaultMessages(), [
                'confirm_approve' => __('Opravdu chcete schválit tuto realizaci?', 'mistr-fachman'),
                'confirm_reject' => __('Opravdu chcete odmítnout tuto realizaci?', 'mistr-fachman'),
                'confirm_bulk_approve' => __('Opravdu chcete hromadně schválit všechny čekající realizace tohoto uživatele?', 'mistr-fachman')
            ])
        ];
    }

    /**
     * Enqueue status dropdown for realizace post edit pages
     */
    public function enqueue_realizace_status_dropdown(): void {
        error_log('[REALIZACE:ASSET] enqueue_realizace_status_dropdown called');
        
        $this->enqueue_status_dropdown_script([
            'domain' => 'Realizace',
            'postType' => 'realizace',
            'customStatus' => 'rejected',
            'customStatusLabel' => 'Odmítnuto',
            'currentStatus' => get_post()->post_status ?? '',
            'debugPrefix' => 'REALIZACE'
        ]);
        
        error_log('[REALIZACE:ASSET] enqueue_realizace_status_dropdown completed');
    }

    /**
     * Get localized script data for AJAX requests (backward compatibility)
     */
    public function get_localized_data(): array {
        return $this->getBaseLocalizationData();
    }
}