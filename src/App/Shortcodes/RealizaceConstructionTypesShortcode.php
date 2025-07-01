<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\Realizace\TaxonomyManager;

/**
 * Realizace Construction Types Shortcode
 *
 * Renders a select dropdown with construction types for Realizace forms.
 * Integrates with Alpine.js for reactive material filtering.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RealizaceConstructionTypesShortcode extends ShortcodeBase {

    protected array $default_attributes = [
        'name' => 'construction-types',
        'class' => 'realizace-construction-types',
        'placeholder' => 'Vyberte typ konstrukce...',
        'multiple' => 'true'
    ];

    /**
     * Get the shortcode tag name
     */
    public function get_tag(): string {
        return 'realizace_construction_types';
    }

    /**
     * Render the shortcode output
     */
    protected function render(array $attributes, ?string $content = null): string {
        // Enqueue assets for this shortcode
        $this->enqueue_assets();

        $construction_types = TaxonomyManager::get_construction_types();

        if (empty($construction_types)) {
            return $this->renderErrorMessage('Žádné typy konstrukcí nejsou k dispozici.');
        }

        $name_attr = $attributes['multiple'] === 'true' ? $attributes['name'] . '[]' : $attributes['name'];
        $multiple_attr = $attributes['multiple'] === 'true' ? 'multiple' : '';

        return $this->renderConstructionTypesSelect($name_attr, $multiple_attr, $attributes, $construction_types);
    }

    /**
     * Render error message component
     */
    private function renderErrorMessage(string $message): string {
        return '<p class="realizace-error">' . esc_html($message) . '</p>';
    }

    /**
     * Render construction types select component
     */
    private function renderConstructionTypesSelect(string $name_attr, string $multiple_attr, array $attributes, array $construction_types): string {
        ob_start();
        ?>
        <div class="realizace-select-wrapper <?= esc_attr($attributes['class']) ?>" x-data="constructionSelector">
            <select 
                name="<?= esc_attr($name_attr) ?>" 
                class="realizace-select <?= esc_attr($attributes['class']) ?>" 
                <?= esc_attr($multiple_attr) ?> 
                x-model="selectedTypes" 
                @change="updateMaterials()"
            >
                <option value="" disabled :selected="selectedTypes.length === 0">
                    <?= esc_html($attributes['placeholder']) ?>
                </option>
                <?php foreach ($construction_types as $type): ?>
                    <option value="<?= esc_attr($type->term_id) ?>">
                        <?= esc_html($type->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue necessary assets
     */
    private function enqueue_assets(): void {
        // Only enqueue if Alpine.js is not already loaded
        if (!wp_script_is('alpine-js', 'enqueued')) {
            wp_enqueue_script(
                'alpine-js',
                'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
                [],
                '3.0.0',
                true
            );
            wp_script_add_data('alpine-js', 'defer', true);
        }

        // Add inline script for Alpine.js components (only once)
        if (!wp_script_is('realizace-alpine-components', 'done')) {
            wp_add_inline_script('alpine-js', $this->get_alpine_components(), 'before');
            
            // Localize script for AJAX
            wp_localize_script('alpine-js', 'realizaceAjax', [
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mistr_fachman_access_control')
            ]);

            // Mark as done to prevent duplicate registration
            wp_script_add_data('realizace-alpine-components', 'done', true);
        }
    }

    /**
     * Get Alpine.js component definitions
     */
    private function get_alpine_components(): string {
        return "
        document.addEventListener('alpine:init', () => {
            console.log('[RealizaceAlpine] Alpine.js initializing construction selector');
            Alpine.data('constructionSelector', () => ({
                selectedTypes: [],
                
                updateMaterials() {
                    console.log('[RealizaceAlpine] updateMaterials called with selectedTypes:', this.selectedTypes);
                    
                    // Filter out empty values and placeholder text
                    let validTypes = this.selectedTypes;
                    if (Array.isArray(validTypes)) {
                        validTypes = validTypes.filter(type => type && type !== '' && type !== 'Vyberte typ konstrukce...');
                    }
                    console.log('[RealizaceAlpine] Filtered validTypes:', validTypes);
                    
                    // Dispatch event for materials selector to listen
                    window.dispatchEvent(new CustomEvent('construction-types-changed', {
                        detail: { selectedTypes: validTypes }
                    }));
                    console.log('[RealizaceAlpine] Dispatched construction-types-changed event');
                }
            }));
            
            Alpine.data('materialsSelector', () => ({
                selectedMaterials: [],
                availableMaterials: [],
                loading: false,
                
                init() {
                    console.log('[RealizaceAlpine] Materials selector initialized');
                    // Listen for construction type changes
                    window.addEventListener('construction-types-changed', (event) => {
                        console.log('[RealizaceAlpine] Received construction-types-changed event:', event.detail);
                        this.loadMaterials(event.detail.selectedTypes);
                    });
                },
                
                async loadMaterials(constructionTypes) {
                    console.log('[RealizaceAlpine] loadMaterials called with:', constructionTypes);
                    if (!constructionTypes || constructionTypes.length === 0) {
                        console.log('[RealizaceAlpine] No construction types selected, clearing materials');
                        this.availableMaterials = [];
                        this.selectedMaterials = [];
                        return;
                    }
                    
                    console.log('[RealizaceAlpine] Starting AJAX request for materials');
                    this.loading = true;
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'get_allowed_materials');
                        formData.append('nonce', realizaceAjax.nonce);
                        
                        // Handle both single and multiple selection
                        if (Array.isArray(constructionTypes)) {
                            constructionTypes.forEach(type => {
                                formData.append('construction_types[]', type);
                            });
                        } else {
                            formData.append('construction_types[]', constructionTypes);
                        }
                        
                        console.log('[RealizaceAlpine] AJAX URL:', realizaceAjax.url);
                        console.log('[RealizaceAlpine] Form data:', Object.fromEntries(formData));
                        
                        const response = await fetch(realizaceAjax.url, {
                            method: 'POST',
                            body: formData
                        });
                        
                        console.log('[RealizaceAlpine] AJAX response status:', response.status);
                        const result = await response.json();
                        console.log('[RealizaceAlpine] AJAX response data:', result);
                        
                        if (result.success) {
                            this.availableMaterials = result.data || [];
                            console.log('[RealizaceAlpine] Set availableMaterials to:', this.availableMaterials);
                            // Clear selected materials that are no longer available
                            this.selectedMaterials = this.selectedMaterials.filter(selected =>
                                this.availableMaterials.some(available => available.id == selected)
                            );
                        } else {
                            console.error('[RealizaceAlpine] Failed to load materials:', result.data);
                            this.availableMaterials = [];
                        }
                    } catch (error) {
                        console.error('[RealizaceAlpine] Error loading materials:', error);
                        this.availableMaterials = [];
                    } finally {
                        this.loading = false;
                        console.log('[RealizaceAlpine] AJAX request completed');
                    }
                }
            }));
        });
        ";
    }

    /**
     * Override sanitize_attributes for construction types specific validation
     */
    protected function sanitize_attributes(array $attributes): array {
        $sanitized = [];

        foreach ($attributes as $key => $value) {
            $sanitized[$key] = match ($key) {
                'multiple' => in_array($value, ['true', 'false'], true) ? $value : 'true',
                'name' => preg_replace('/[^a-zA-Z0-9_-]/', '', $value),
                'class' => sanitize_html_class($value),
                default => sanitize_text_field($value)
            };
        }

        return $sanitized;
    }
}