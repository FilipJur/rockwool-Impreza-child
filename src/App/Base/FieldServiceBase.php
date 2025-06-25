<?php

declare(strict_types=1);

namespace MistrFachman\Base;

/**
 * Field Service Base Class
 *
 * Provides shared functionality for ACF field access with meta fallback.
 * Eliminates duplication between domain-specific field services.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class FieldServiceBase {

    /**
     * Get field value with ACF fallback to post meta
     *
     * @param string $field_selector Field selector/name
     * @param int $post_id Post ID
     * @return mixed Field value
     */
    protected static function getFieldValue(string $field_selector, int $post_id) {
        if (function_exists('get_field')) {
            return get_field($field_selector, $post_id);
        }
        
        return get_post_meta($post_id, $field_selector, true);
    }

    /**
     * Set field value with ACF fallback to post meta
     *
     * @param string $field_selector Field selector/name
     * @param int $post_id Post ID
     * @param mixed $value Value to set
     * @return bool Success status
     */
    protected static function setFieldValueInternal(string $field_selector, int $post_id, $value): bool {
        if (function_exists('update_field')) {
            $result = update_field($field_selector, $value, $post_id);
            return $result !== false;
        }
        
        return update_post_meta($post_id, $field_selector, $value) !== false;
    }

    /**
     * Public helper: Set any field value with ACF fallback
     * 
     * Exposed as public interface for external usage
     *
     * @param string $field_selector Field selector
     * @param int $post_id Post ID
     * @param mixed $value Value to set
     * @return bool Success status
     */
    public static function setFieldValue(string $field_selector, int $post_id, $value): bool {
        return static::setFieldValueInternal($field_selector, $post_id, $value);
    }
}