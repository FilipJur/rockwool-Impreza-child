<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

use MistrFachman\Base\FieldServiceBase;

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

class RealizaceFieldService extends FieldServiceBase {

    /**
     * Field selector constants - Single source of truth
     */
    private const POINTS_FIELD = 'points_assigned';
    private const REJECTION_REASON_FIELD = 'rejection_reason';
    private const GALLERY_FIELD = 'realization_gallery';
    private const AREA_FIELD = 'area_sqm';
    private const CONSTRUCTION_TYPE_FIELD = 'construction_type';
    private const MATERIALS_FIELD = 'materials_used';

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