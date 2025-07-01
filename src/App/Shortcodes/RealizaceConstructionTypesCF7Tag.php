<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\Realizace\TaxonomyManager;

/**
 * Contact Form 7 Custom Form Tag: Realizace Construction Types
 *
 * Renders a select dropdown with construction types for CF7 forms.
 * Integrates with Alpine.js for reactive material filtering.
 *
 * Usage in CF7: [realizace_construction_types your-construction-types]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RealizaceConstructionTypesCF7Tag extends CF7FormTagBase {

    // Constants for better maintainability
    private const DEFAULT_NAME = 'construction-types';
    private const DEFAULT_CLASS = 'realizace-construction-types';
    private const DEFAULT_PLACEHOLDER = 'Vyberte typ konstrukce...';
    private const ERROR_NO_TYPES = 'Žádné typy konstrukcí nejsou k dispozici.';
    private const ALPINE_COMPONENT_NAME = 'constructionSelector';
    private const ERROR_LOADING_FAILED = 'Nepodařilo se načíst materiály. Zkuste to prosím znovu.';
    private const ERROR_NETWORK_ERROR = 'Chyba při komunikaci se serverem. Zkontrolujte připojení k internetu.';

    protected array $default_attributes = [
        'name' => self::DEFAULT_NAME,
        'class' => self::DEFAULT_CLASS,
        'placeholder' => self::DEFAULT_PLACEHOLDER,
        'multiple' => 'true'
    ];

    /**
     * Get the CF7 form tag name
     */
    public function get_cf7_tag(): string {
        return 'realizace_construction_types';
    }

    /**
     * Render the form tag output
     */
    protected function render(array $attributes, ?string $content = null): string {
        // Enqueue assets for this form tag
        $this->enqueue_assets();

        $construction_types = TaxonomyManager::get_construction_types();

        if (empty($construction_types)) {
            return $this->renderErrorMessage(self::ERROR_NO_TYPES);
        }

        $name_attr = $attributes['multiple'] === 'true' ? $attributes['name'] . '[]' : $attributes['name'];
        $multiple_attr = $attributes['multiple'] === 'true' ? 'multiple' : '';
        $required_attr = isset($attributes['required']) ? 'required' : '';

        return $this->renderSelect($name_attr, $multiple_attr, $required_attr, $attributes['placeholder'], $construction_types);
    }

    /**
     * Render error message component
     */
    private function renderErrorMessage(string $message): string {
        return '<p class="realizace-error">' . esc_html($message) . '</p>';
    }

    /**
     * Render select component
     */
    private function renderSelect(string $name_attr, string $multiple_attr, string $required_attr, string $placeholder, array $construction_types): string {
        ob_start();
        ?>
        <div class="realizace-select-wrapper" x-data="<?= esc_attr(self::ALPINE_COMPONENT_NAME) ?>">
            <select 
                name="<?= esc_attr($name_attr) ?>" 
                <?= esc_attr($multiple_attr) ?> 
                <?= esc_attr($required_attr) ?> 
                x-model="selectedTypes" 
                @change="updateMaterials()" 
                class="realizace-construction-types-select"
            >
                <option value="" disabled :selected="selectedTypes.length === 0">
                    <?= esc_html($placeholder) ?>
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
        CF7AssetManager::enqueue_realizace_components();
    }

}