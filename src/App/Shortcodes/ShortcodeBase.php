<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * Abstract Base Class for Shortcode Components
 *
 * Provides common functionality for all shortcode components including
 * parameter validation, template rendering, and access to myCred services.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class ShortcodeBase {

    protected Manager $ecommerce_manager;
    protected ProductService $product_service;
    protected UserService $user_service;
    protected array $default_attributes = [];
    protected array $required_attributes = [];

    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service) {
        $this->ecommerce_manager = $ecommerce_manager;
        $this->product_service = $product_service;
        $this->user_service = $user_service;
    }

    /**
     * Get the shortcode tag name
     * 
     * @return string The shortcode tag (e.g., 'mycred_products')
     */
    abstract public function get_tag(): string;

    /**
     * Render the shortcode output
     * 
     * @param array $attributes Shortcode attributes
     * @param string|null $content Shortcode content
     * @return string Rendered HTML output
     */
    abstract protected function render(array $attributes, ?string $content = null): string;

    /**
     * Handle shortcode execution (public interface for WordPress)
     * 
     * @param array $attributes Raw shortcode attributes
     * @param string|null $content Shortcode content
     * @return string Rendered HTML output
     */
    public function handle_shortcode(array $attributes = [], ?string $content = null): string {
        try {
            // Validate and sanitize attributes
            $validated_attributes = $this->validate_attributes($attributes);

            // Render the shortcode
            return $this->render($validated_attributes, $content);

        } catch (\Exception $e) {
            mycred_debug('Shortcode error', [
                'shortcode' => $this->get_tag(),
                'attributes' => $attributes,
                'error' => $e->getMessage()
            ], 'shortcode', 'error');

            return $this->render_error($e->getMessage());
        }
    }

    /**
     * Validate and sanitize shortcode attributes
     * 
     * @param array $attributes Raw attributes
     * @return array Validated attributes
     * @throws \InvalidArgumentException If required attributes are missing
     */
    protected function validate_attributes(array $attributes): array {
        // Merge with defaults
        $attributes = shortcode_atts($this->default_attributes, $attributes, $this->get_tag());

        // Check required attributes
        foreach ($this->required_attributes as $required) {
            if (empty($attributes[$required])) {
                throw new \InvalidArgumentException(
                    'Required attribute "' . $required . '" is missing for shortcode [' . $this->get_tag() . ']'
                );
            }
        }

        // Sanitize attributes
        return $this->sanitize_attributes($attributes);
    }

    /**
     * Sanitize individual attributes
     * 
     * @param array $attributes Attributes to sanitize
     * @return array Sanitized attributes
     */
    protected function sanitize_attributes(array $attributes): array {
        $sanitized = [];

        foreach ($attributes as $key => $value) {
            $sanitized[$key] = match ($key) {
                'balance_filter' => in_array($value, ['available', 'affordable', 'unavailable', 'strictly_available', 'strictly_unavailable', 'dynamic'], true) ? $value : 'dynamic',
                'columns', 'limit' => absint($value),
                'order' => in_array(strtoupper($value), ['ASC', 'DESC'], true) ? strtoupper($value) : 'ASC',
                'orderby' => in_array($value, ['title', 'date', 'price', 'menu_order', 'rand'], true) ? $value : 'title',
                default => sanitize_text_field($value)
            };
        }

        return $sanitized;
    }


    /**
     * Render error message
     * 
     * @param string $error_message Error message
     * @return string HTML output for error
     */
    protected function render_error(string $error_message): string {
        if (current_user_can('manage_options')) {
            return $this->renderAdminError($error_message);
        }

        return '<div class="mycred-shortcode-error">Došlo k chybě při načítání obsahu.</div>';
    }

    /**
     * Render admin error message component
     */
    private function renderAdminError(string $error_message): string {
        ob_start();
        ?>
        <div class="mycred-shortcode-error">
            Chyba v shortcode [<?= esc_html($this->get_tag()) ?>]: <?= esc_html($error_message) ?>
        </div>
        <?php
        return ob_get_clean();
    }



    /**
     * Get wrapper CSS classes for shortcode output
     * 
     * @param array $attributes Shortcode attributes
     * @return string CSS classes
     */
    protected function get_wrapper_classes(array $attributes): string {
        $classes = [
            'mycred-shortcode',
            'mycred-' . str_replace('_', '-', $this->get_tag())
        ];

        if (!empty($attributes['class'])) {
            $classes[] = sanitize_html_class($attributes['class']);
        }

        return implode(' ', $classes);
    }

    /**
     * Load template with data - unified template loading system
     * 
     * @param string $template_name Template file name (e.g., 'zebricek/leaderboard.php')
     * @param array $data Data to extract as variables for the template
     * @return string Rendered template content
     */
    protected function load_template(string $template_name, array $data = []): string
    {
        $template_path = get_stylesheet_directory() . '/templates/' . $template_name;
        
        if (!file_exists($template_path)) {
            if (current_user_can('manage_options')) {
                return $this->render_error("Template not found: {$template_name}");
            }
            return '<div class="mycred-shortcode-error">Došlo k chybě při načítání obsahu.</div>';
        }

        // Extract data to variables for template
        extract($data, EXTR_SKIP);
        
        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Register AJAX hooks for this shortcode (optional method)
     * Override this method in child classes that need AJAX functionality
     */
    public function register_ajax_hooks(): void {
        // Default implementation - no AJAX hooks to register
        // Child classes can override this method to register their specific AJAX hooks
    }

}