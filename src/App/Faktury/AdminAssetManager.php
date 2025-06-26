<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\AdminAssetManagerBase;

/**
 * Faktury Admin Asset Manager - Domain-Specific Asset Management
 *
 * Extends AdminAssetManagerBase to provide faktury-specific asset management.
 * Handles CSS, JavaScript, and localization for faktury admin interface.
 * Mirrors Realizace AdminAssetManager pattern exactly.
 *
 * TODO: Implement faktury-specific localization data when UI is built
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
        return 'invoice';
    }

    /**
     * Get the domain-specific localization object name
     */
    protected function getLocalizationObjectName(): string {
        return 'mistrFakturyAdmin';
    }

    /**
     * Get domain-specific admin screen IDs where assets should load
     */
    protected function getAdminScreenIds(): array {
        return ['user-edit', 'profile', 'users', 'post', 'faktura'];
    }

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
     * Get domain-specific localized data for scripts
     */
    protected function getDomainLocalizationData(): array {
        return [
            'field_names' => [
                'rejection_reason' => FakturaFieldService::getRejectionReasonFieldSelector(),
                'points' => FakturaFieldService::getPointsFieldSelector(),
                'value' => FakturaFieldService::getValueFieldSelector(),
                'file' => FakturaFieldService::getFileFieldSelector(),
                'invoice_number' => FakturaFieldService::getInvoiceNumberFieldSelector(),
                'invoice_date' => FakturaFieldService::getInvoiceDateFieldSelector()
            ],
            'default_values' => [
                // No default points for faktury - dynamic calculation only
            ],
            'messages' => array_merge($this->getDefaultMessages(), [
                'confirm_approve' => __('Opravdu chcete schválit tuto fakturu?', 'mistr-fachman'),
                'confirm_reject' => __('Opravdu chcete odmítnout tuto fakturu?', 'mistr-fachman'),
                'confirm_bulk_approve' => __('Opravdu chcete hromadně schválit všechny čekající faktury tohoto uživatele?', 'mistr-fachman')
            ])
        ];
    }

    /**
     * Handle faktury-specific asset loading
     * Implements status dropdown for post edit pages
     */
    protected function handle_domain_specific_assets(): void {
        global $post;
        
        $screen = get_current_screen();
        if ($screen && $screen->base === 'post' && $post && $post->post_type === 'invoice') {
            $this->enqueue_status_dropdown_script([
                'domain'            => 'Faktury',
                'postType'          => 'invoice',
                'customStatus'      => 'rejected',
                'customStatusLabel' => __('Odmítnuto', 'mistr-fachman'),
                'currentStatus'     => $post->post_status ?? '',
                'debugPrefix'       => 'FAKTURY'
            ]);
        }
    }

    /**
     * Get localized script data for AJAX requests (backward compatibility)
     */
    public function get_localized_data(): array {
        return $this->getBaseLocalizationData();
    }
}