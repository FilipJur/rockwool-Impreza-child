<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;

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

    protected Manager $mycred_manager;
    protected array $default_attributes = [];
    protected array $required_attributes = [];

    public function __construct(Manager $mycred_manager) {
        $this->mycred_manager = $mycred_manager;
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
            
            // Check if user has permission to view this shortcode
            if (!$this->check_permissions($validated_attributes)) {
                return $this->render_permission_denied();
            }

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
                    sprintf('Required attribute "%s" is missing for shortcode [%s]', $required, $this->get_tag())
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
                'balance_filter' => $this->sanitize_balance_filter($value),
                'columns' => absint($value),
                'limit' => absint($value),
                'category' => sanitize_text_field($value),
                'order' => $this->sanitize_order($value),
                'orderby' => $this->sanitize_orderby($value),
                default => sanitize_text_field($value)
            };
        }

        return $sanitized;
    }

    /**
     * Sanitize balance filter parameter
     */
    protected function sanitize_balance_filter(string $value): string {
        $valid_filters = ['all', 'affordable', 'unavailable'];
        return in_array($value, $valid_filters, true) ? $value : 'all';
    }

    /**
     * Sanitize order parameter
     */
    protected function sanitize_order(string $value): string {
        $valid_orders = ['ASC', 'DESC'];
        return in_array(strtoupper($value), $valid_orders, true) ? strtoupper($value) : 'ASC';
    }

    /**
     * Sanitize orderby parameter
     */
    protected function sanitize_orderby(string $value): string {
        $valid_orderby = ['title', 'date', 'price', 'menu_order', 'rand'];
        return in_array($value, $valid_orderby, true) ? $value : 'title';
    }

    /**
     * Check if current user has permission to view this shortcode
     * 
     * @param array $attributes Validated attributes
     * @return bool True if user has permission
     */
    protected function check_permissions(array $attributes): bool {
        // Default: allow all logged-in users
        return is_user_logged_in();
    }

    /**
     * Render permission denied message
     * 
     * @return string HTML output for permission denied
     */
    protected function render_permission_denied(): string {
        return '<div class="mycred-shortcode-error">Pro zobrazení tohoto obsahu se musíte přihlásit.</div>';
    }

    /**
     * Render error message
     * 
     * @param string $error_message Error message
     * @return string HTML output for error
     */
    protected function render_error(string $error_message): string {
        if (current_user_can('manage_options')) {
            return sprintf(
                '<div class="mycred-shortcode-error">Chyba v shortcode [%s]: %s</div>',
                esc_html($this->get_tag()),
                esc_html($error_message)
            );
        }

        return '<div class="mycred-shortcode-error">Došlo k chybě při načítání obsahu.</div>';
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

}