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

class RealizaceMaterialsCF7Tag extends CF7FormTagBase
{

	// Constants for better maintainability
	private const DEFAULT_NAME = 'materials';
	private const DEFAULT_CLASS = 'realizace-materials';
	private const DEFAULT_PLACEHOLDER = 'Nejdříve vyberte typ konstrukce...';
	private const ALPINE_COMPONENT_NAME = 'materialsSelector';
	private const LOADING_MESSAGE = 'Načítání materiálů...';

	protected array $default_attributes = [
		'name' => self::DEFAULT_NAME,
		'class' => self::DEFAULT_CLASS,
		'placeholder' => self::DEFAULT_PLACEHOLDER,
		'multiple' => 'true'
	];

	/**
	 * Get the CF7 form tag name
	 */
	public function get_cf7_tag(): string
	{
		return 'realizace_materials';
	}

	/**
	 * Render the form tag output
	 */
	protected function render(array $attributes, ?string $content = null): string
	{
		// Enqueue assets for this form tag
		$this->enqueue_assets();

		$name_attr = $attributes['multiple'] === 'true' ? $attributes['name'] . '[]' : $attributes['name'];
		$multiple_attr = $attributes['multiple'] === 'true' ? 'multiple' : '';
		$required_attr = isset($attributes['required']) ? 'required' : '';

		return $this->renderMaterialsSelect($name_attr, $multiple_attr, $required_attr, $attributes['placeholder']);
	}

	/**
	 * Enqueue necessary assets
	 */
	private function enqueue_assets(): void
	{
		CF7AssetManager::enqueue_realizace_components();
	}

	/**
	 * Render materials select component
	 */
	private function renderMaterialsSelect(string $name_attr, string $multiple_attr, string $required_attr, string $placeholder): string
	{
		ob_start();
		?>
		<div class="realizace-select-wrapper" x-data="<?= esc_attr(self::ALPINE_COMPONENT_NAME) ?>">
			<select name="<?= esc_attr($name_attr) ?>" <?= esc_attr($multiple_attr) ?> <?= esc_attr($required_attr) ?>
				x-model="selectedMaterials" :disabled="availableMaterials.length === 0 || loading"
				class="realizace-materials-select" :class="{ 'loading': loading }">
				<option value="" disabled :selected="selectedMaterials.length === 0">
					<?= esc_html($placeholder) ?>
				</option>
				<template x-for="material in availableMaterials" :key="material.id">
					<option :value="material.id" x-text="material.name"></option>
				</template>
			</select>

			<?php echo $this->renderLoadingIndicator(); ?>
			<?php echo $this->renderErrorMessage(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render loading indicator component
	 */
	private function renderLoadingIndicator(): string
	{
		ob_start();
		?>
		<div x-show="loading" class="realizace-loading">
			<div class="loading-spinner"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render error message component
	 */
	private function renderErrorMessage(): string
	{
		ob_start();
		?>
		<div x-show="errorMessage" x-text="errorMessage" class="realizace-error"></div>
		<?php
		return ob_get_clean();
	}
}
