<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Icon Helper Service
 *
 * Provides reusable SVG icons for components throughout the application.
 * Centralizes icon assets to prevent duplication and ensure consistency.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class IconHelper
{
    /**
     * Get SVG icon by name
     *
     * @param string $name Icon name
     * @param array $attributes Optional attributes (class, width, height, etc.)
     * @return string SVG markup
     */
    public static function get_icon(string $name, array $attributes = []): string
    {
        $icons = [
            'lock' => self::get_lock_icon($attributes),
            // Add more icons here as needed
        ];

        return $icons[$name] ?? '';
    }

    /**
     * Get lock icon SVG
     *
     * @param array $attributes Optional attributes
     * @return string SVG markup
     */
    private static function get_lock_icon(array $attributes = []): string
    {
        $default_attributes = [
            'class' => 'lock-icon',
            'width' => '17',
            'height' => '17',
            'viewBox' => '0 0 17 17',
            'fill' => 'none',
            'xmlns' => 'http://www.w3.org/2000/svg'
        ];

        $attributes = array_merge($default_attributes, $attributes);
        $attr_string = self::build_attributes($attributes);

        return sprintf(
            '<svg %s><path d="M13.429 7.56853H14.0912C14.2668 7.56853 14.4352 7.63829 14.5594 7.76246C14.6836 7.88664 14.7533 8.05505 14.7533 8.23066V14.852C14.7533 15.0276 14.6836 15.196 14.5594 15.3202C14.4352 15.4443 14.2668 15.5141 14.0912 15.5141H3.49709C3.32148 15.5141 3.15307 15.4443 3.02889 15.3202C2.90472 15.196 2.83496 15.0276 2.83496 14.852V8.23066C2.83496 8.05505 2.90472 7.88664 3.02889 7.76246C3.15307 7.63829 3.32148 7.56853 3.49709 7.56853H4.15922V6.9064C4.15922 5.67714 4.64754 4.49823 5.51676 3.62902C6.38597 2.7598 7.56488 2.27148 8.79414 2.27148C10.0234 2.27148 11.2023 2.7598 12.0715 3.62902C12.9407 4.49823 13.429 5.67714 13.429 6.9064V7.56853ZM4.15922 8.89279V14.1898H13.429V8.89279H4.15922ZM8.13201 10.2171H9.45627V12.8656H8.13201V10.2171ZM12.1048 7.56853V6.9064C12.1048 6.02836 11.756 5.18628 11.1351 4.56541C10.5143 3.94455 9.67218 3.59575 8.79414 3.59575C7.9161 3.59575 7.07402 3.94455 6.45315 4.56541C5.83228 5.18628 5.48348 6.02836 5.48348 6.9064V7.56853H12.1048Z" fill="#909090"/></svg>',
            $attr_string
        );
    }

    /**
     * Build HTML attributes string from array
     *
     * @param array $attributes
     * @return string
     */
    private static function build_attributes(array $attributes): string
    {
        $attr_parts = [];
        foreach ($attributes as $key => $value) {
            $attr_parts[] = sprintf('%s="%s"', esc_attr($key), esc_attr($value));
        }
        return implode(' ', $attr_parts);
    }
}