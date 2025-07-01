<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

/**
 * Contact Form 7 Custom Form Tag: Realizace Materials
 *
 * Renders a select dropdown with materials filtered by construction types.
 * Works in conjunction with RealizaceConstructionTypesCF7Tag for reactive filtering.
 *
 * Usage in CF7: [realizace_materials your-materials]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RealizaceMaterialsCF7Tag extends CF7FormTagBase {

    protected array $default_attributes = [
        'name' => 'materials',
        'class' => 'realizace-materials',
        'placeholder' => 'Nejdříve vyberte typ konstrukce...',
        'multiple' => 'true'
    ];

    /**
     * Get the CF7 form tag name
     */
    public function get_cf7_tag(): string {
        return 'realizace_materials';
    }

    /**
     * Render the form tag output
     */
    protected function render(array $attributes, ?string $content = null): string {
        $multiple_attr = $attributes['multiple'] === 'true' ? 'multiple' : '';
        $required_attr = isset($attributes['required']) ? 'required' : '';
        $name_attr = $attributes['multiple'] === 'true' ? $attributes['name'] . '[]' : $attributes['name'];

        return sprintf(
            '<div class="realizace-select-wrapper" x-data="materialsSelector">
                <select name="%s" %s %s x-model="selectedMaterials" :disabled="availableMaterials.length === 0">
                    <option value="" disabled :selected="selectedMaterials.length === 0">%s</option>
                    <template x-for="material in availableMaterials" :key="material.id">
                        <option :value="material.id" x-text="material.name"></option>
                    </template>
                </select>
                <div x-show="loading">Načítání materiálů...</div>
            </div>',
            esc_attr($name_attr),
            $multiple_attr,
            $required_attr,
            esc_html($attributes['placeholder'])
        );
    }
}