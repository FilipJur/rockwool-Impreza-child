<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

/**
 * Realizace Materials Shortcode
 *
 * Renders a select dropdown with materials filtered by construction types.
 * Works in conjunction with RealizaceConstructionTypesShortcode for reactive filtering.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RealizaceMaterialsShortcode extends ShortcodeBase {

    protected array $default_attributes = [
        'name' => 'materials',
        'class' => 'realizace-materials',
        'placeholder' => 'Nejdříve vyberte typ konstrukce...',
        'multiple' => 'true'
    ];

    /**
     * Get the shortcode tag name
     */
    public function get_tag(): string {
        return 'realizace_materials';
    }

    /**
     * Render the shortcode output
     */
    protected function render(array $attributes, ?string $content = null): string {
        $multiple_attr = $attributes['multiple'] === 'true' ? 'multiple' : '';
        $name_attr = $attributes['multiple'] === 'true' ? $attributes['name'] . '[]' : $attributes['name'];

        return sprintf(
            '<div class="realizace-select-wrapper %s" x-data="materialsSelector">
                <select name="%s" class="realizace-select %s" %s x-model="selectedMaterials" :disabled="availableMaterials.length === 0">
                    <option value="" disabled :selected="selectedMaterials.length === 0">%s</option>
                    <template x-for="material in availableMaterials" :key="material.id">
                        <option :value="material.id" x-text="material.name"></option>
                    </template>
                </select>
                <div x-show="loading" class="realizace-loading">Načítání materiálů...</div>
            </div>',
            esc_attr($attributes['class']),
            esc_attr($name_attr),
            esc_attr($attributes['class']),
            $multiple_attr,
            esc_html($attributes['placeholder'])
        );
    }

    /**
     * Override sanitize_attributes for materials specific validation
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