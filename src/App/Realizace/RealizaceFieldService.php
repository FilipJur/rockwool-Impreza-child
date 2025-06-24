<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Field Service - Centralized Field Access
 *
 * Provides a single source of truth for all realizace field access.
 * Eliminates hardcoded field names and ensures consistent field access patterns.
 *
 * Usage:
 * - RealizaceFieldService::getPoints($post_id)
 * - RealizaceFieldService::getRejectionReason($post_id)
 * - RealizaceFieldService::setPoints($post_id, $points)
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RealizaceFieldService {

    /**
     * Field selector constants - Single source of truth
     */
    private const POINTS_FIELD = 'sprava_a_hodnoceni_realizace_pridelene_body';
    private const REJECTION_REASON_FIELD = 'sprava_a_hodnoceni_realizace_duvod_zamitnuti';
    private const GALLERY_FIELD = 'fotky_realizace';
    private const AREA_FIELD = 'pocet_m2';
    private const CONSTRUCTION_TYPE_FIELD = 'typ_konstrukce';
    private const MATERIALS_FIELD = 'pouzite_materialy';

    /**
     * Get points value for a realizace post
     *
     * @param int $post_id Post ID
     * @return int Points value
     */
    public static function getPoints(int $post_id): int {
        $value = self::getFieldValue(self::POINTS_FIELD, $post_id);
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * Set points value for a realizace post
     *
     * @param int $post_id Post ID
     * @param int $points Points to set
     * @return bool Success status
     */
    public static function setPoints(int $post_id, int $points): bool {
        return self::setFieldValueInternal(self::POINTS_FIELD, $post_id, $points);
    }

    /**
     * Get rejection reason for a realizace post
     *
     * @param int $post_id Post ID
     * @return string Rejection reason
     */
    public static function getRejectionReason(int $post_id): string {
        $value = self::getFieldValue(self::REJECTION_REASON_FIELD, $post_id);
        return is_string($value) ? $value : '';
    }

    /**
     * Set rejection reason for a realizace post
     *
     * @param int $post_id Post ID
     * @param string $reason Rejection reason
     * @return bool Success status
     */
    public static function setRejectionReason(int $post_id, string $reason): bool {
        return self::setFieldValueInternal(self::REJECTION_REASON_FIELD, $post_id, $reason);
    }

    /**
     * Get gallery images for a realizace post
     *
     * @param int $post_id Post ID
     * @return array Gallery images
     */
    public static function getGallery(int $post_id): array {
        $value = self::getFieldValue(self::GALLERY_FIELD, $post_id);
        return is_array($value) ? $value : [];
    }

    /**
     * Get area/size for a realizace post
     *
     * @param int $post_id Post ID
     * @return string Area value
     */
    public static function getArea(int $post_id): string {
        $value = self::getFieldValue(self::AREA_FIELD, $post_id);
        return is_string($value) ? $value : '';
    }

    /**
     * Get construction type for a realizace post
     *
     * @param int $post_id Post ID
     * @return string Construction type
     */
    public static function getConstructionType(int $post_id): string {
        $value = self::getFieldValue(self::CONSTRUCTION_TYPE_FIELD, $post_id);
        return is_string($value) ? $value : '';
    }

    /**
     * Get materials for a realizace post
     *
     * @param int $post_id Post ID
     * @return string Materials
     */
    public static function getMaterials(int $post_id): string {
        $value = self::getFieldValue(self::MATERIALS_FIELD, $post_id);
        return is_string($value) ? $value : '';
    }

    /**
     * Get field selector constants for use in other classes
     */
    public static function getPointsFieldSelector(): string {
        return self::POINTS_FIELD;
    }

    public static function getRejectionReasonFieldSelector(): string {
        return self::REJECTION_REASON_FIELD;
    }

    public static function getGalleryFieldSelector(): string {
        return self::GALLERY_FIELD;
    }

    public static function getAreaFieldSelector(): string {
        return self::AREA_FIELD;
    }

    public static function getConstructionTypeFieldSelector(): string {
        return self::CONSTRUCTION_TYPE_FIELD;
    }

    public static function getMaterialsFieldSelector(): string {
        return self::MATERIALS_FIELD;
    }

    /**
     * Private helper: Get field value with ACF fallback
     *
     * @param string $field_selector Field selector
     * @param int $post_id Post ID
     * @return mixed Field value
     */
    private static function getFieldValue(string $field_selector, int $post_id) {
        if (function_exists('get_field')) {
            return get_field($field_selector, $post_id);
        }
        
        return get_post_meta($post_id, $field_selector, true);
    }

    /**
     * Public helper: Set any field value with ACF fallback
     *
     * @param string $field_selector Field selector
     * @param int $post_id Post ID
     * @param mixed $value Value to set
     * @return bool Success status
     */
    public static function setFieldValue(string $field_selector, int $post_id, $value): bool {
        return self::setFieldValueInternal($field_selector, $post_id, $value);
    }

    /**
     * Private helper: Set field value with ACF fallback
     *
     * @param string $field_selector Field selector
     * @param int $post_id Post ID
     * @param mixed $value Value to set
     * @return bool Success status
     */
    private static function setFieldValueInternal(string $field_selector, int $post_id, $value): bool {
        if (function_exists('update_field')) {
            $result = update_field($field_selector, $value, $post_id);
            return $result !== false;
        }
        
        return update_post_meta($post_id, $field_selector, $value) !== false;
    }

    /**
     * Get all realizace data for a post in one call
     *
     * @param int $post_id Post ID
     * @return array Complete realizace data
     */
    public static function getAllFields(int $post_id): array {
        return [
            'points' => self::getPoints($post_id),
            'rejection_reason' => self::getRejectionReason($post_id),
            'gallery' => self::getGallery($post_id),
            'area' => self::getArea($post_id),
            'construction_type' => self::getConstructionType($post_id),
            'materials' => self::getMaterials($post_id),
        ];
    }
}