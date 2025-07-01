<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * Base Class for Contact Form 7 Custom Form Tags
 *
 * Provides common functionality for CF7 form tag components including
 * parameter validation, template rendering, and access to services.
 * Extends ShortcodeBase to reuse existing patterns.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class CF7FormTagBase extends ShortcodeBase {

    /**
     * Get the CF7 form tag name (without brackets)
     * 
     * @return string The form tag name (e.g., 'realizace_construction_types')
     */
    abstract public function get_cf7_tag(): string;

    /**
     * Register this form tag with Contact Form 7
     */
    public function register_cf7_tag(): void {
        if (!function_exists('wpcf7_add_form_tag')) {
            return;
        }

        $tag = $this->get_cf7_tag();
        
        // Register both normal and required versions
        wpcf7_add_form_tag(
            [$tag, $tag . '*'],
            [$this, 'cf7_tag_handler'],
            ['name-attr' => true]
        );
    }

    /**
     * Handle CF7 form tag rendering
     * 
     * @param \WPCF7_FormTag $tag The CF7 form tag object
     * @return string Rendered HTML output
     */
    public function cf7_tag_handler(\WPCF7_FormTag $tag): string {
        try {
            // Convert CF7 tag to shortcode-compatible attributes
            $attributes = $this->convert_cf7_tag_to_attributes($tag);
            
            // Use existing shortcode rendering logic
            return $this->render($attributes);
            
        } catch (\Exception $e) {
            error_log('[CF7FormTag] Error rendering tag: ' . $e->getMessage());
            return '<p class="cf7-error">Error rendering form field</p>';
        }
    }

    /**
     * Convert CF7 FormTag to shortcode attributes
     * 
     * @param \WPCF7_FormTag $tag The CF7 form tag object
     * @return array Shortcode-compatible attributes
     */
    protected function convert_cf7_tag_to_attributes(\WPCF7_FormTag $tag): array {
        $attributes = [];
        
        // Basic attributes (using correct CF7 API)
        $attributes['name'] = $tag->name ?: $this->get_default_name();
        
        // Handle class option safely
        $class_option = $tag->get_class_option();
        if (is_array($class_option)) {
            $attributes['class'] = implode(' ', $class_option) ?: $this->get_default_class();
        } else {
            $attributes['class'] = $class_option ?: $this->get_default_class();
        }
        
        // Required field handling
        if ($tag->is_required()) {
            $attributes['required'] = 'true';
        }
        
        // Multiple selection (from tag options)
        if ($tag->has_option('multiple')) {
            $attributes['multiple'] = 'true';
        }
        
        // Custom placeholder
        $placeholder = $tag->get_option('placeholder');
        if (!empty($placeholder)) {
            $attributes['placeholder'] = $placeholder[0];
        }
        
        // Merge with default attributes
        return array_merge($this->default_attributes, $attributes);
    }

    /**
     * Get default field name for this form tag
     * 
     * @return string Default field name
     */
    protected function get_default_name(): string {
        return str_replace('_', '-', $this->get_cf7_tag());
    }

    /**
     * Get default CSS class for this form tag
     * 
     * @return string Default CSS class
     */
    protected function get_default_class(): string {
        return 'realizace-' . str_replace('_', '-', $this->get_cf7_tag());
    }

    /**
     * Get the shortcode tag name (delegates to CF7 tag)
     */
    public function get_tag(): string {
        return $this->get_cf7_tag();
    }
}