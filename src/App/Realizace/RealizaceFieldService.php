<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

use MistrFachman\Base\FieldServiceBase;
use MistrFachman\Services\DomainConfigurationService;

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
     * Domain key for configuration access
     */
    private const DOMAIN_KEY = 'realization';

    /**
     * Get points value for a realizace post
     *
     * @param int $post_id Post ID
     * @return int Points value
     */
    public static function getPoints(int $post_id): int {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'points'), $post_id);
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * Get awarded points from MyCred transaction history (legacy meta field)
     *
     * @param int $post_id Post ID
     * @return int Awarded points value
     */
    public static function getAwardedPoints(int $post_id): int {
        $value = get_post_meta($post_id, DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'awarded_points_meta'), true);
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
        return self::setFieldValueInternal(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'points'), $post_id, $points);
    }

    /**
     * Get rejection reason for a realizace post
     *
     * @param int $post_id Post ID
     * @return string Rejection reason
     */
    public static function getRejectionReason(int $post_id): string {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'rejection_reason'), $post_id);
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
        return self::setFieldValueInternal(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'rejection_reason'), $post_id, $reason);
    }

    /**
     * Get gallery images for a realizace post
     *
     * @param int $post_id Post ID
     * @return array Gallery images
     */
    public static function getGallery(int $post_id): array {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'gallery'), $post_id);
        return is_array($value) ? $value : [];
    }

    /**
     * Get area/size for a realizace post
     *
     * @param int $post_id Post ID
     * @return string Area value
     */
    public static function getArea(int $post_id): string {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'area'), $post_id);
        return is_string($value) ? $value : '';
    }

    /**
     * Get construction type for a realizace post
     *
     * @param int $post_id Post ID
     * @return string Construction type (backward compatibility - returns first type name)
     */
    public static function getConstructionType(int $post_id): string {
        $types = self::getConstructionTypes($post_id);
        return !empty($types) ? $types[0]->name : '';
    }

    /**
     * Get construction types for a realizace post (new taxonomy-based method)
     *
     * @param int $post_id Post ID
     * @return array Array of term objects
     */
    public static function getConstructionTypes(int $post_id): array {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'construction_type'), $post_id);
        
        // Handle both ACF taxonomy field format and legacy string format
        if (is_array($value)) {
            // ACF taxonomy field returns term objects or IDs
            if (!empty($value) && is_object($value[0])) {
                return $value; // Already term objects
            } elseif (!empty($value) && is_numeric($value[0])) {
                // Term IDs - convert to term objects
                return get_terms([
                    'taxonomy' => 'construction_type',
                    'include' => $value,
                    'hide_empty' => false,
                ]);
            }
        } elseif (is_string($value) && !empty($value)) {
            // Legacy string format - try to find matching term
            $term = get_term_by('name', $value, 'construction_type');
            return $term ? [$term] : [];
        }
        
        return [];
    }

    /**
     * Get construction type IDs for a realizace post
     *
     * @param int $post_id Post ID
     * @return array Array of term IDs
     */
    public static function getConstructionTypeIds(int $post_id): array {
        $types = self::getConstructionTypes($post_id);
        return array_map(fn($term) => $term->term_id, $types);
    }

    /**
     * Get materials for a realizace post
     *
     * @param int $post_id Post ID
     * @return string Materials (backward compatibility - returns comma-separated names)
     */
    public static function getMaterials(int $post_id): string {
        $materials = self::getMaterialsArray($post_id);
        $names = array_map(fn($term) => $term->name, $materials);
        return implode(', ', $names);
    }

    /**
     * Get materials for a realizace post (new taxonomy-based method)
     *
     * @param int $post_id Post ID
     * @return array Array of term objects
     */
    public static function getMaterialsArray(int $post_id): array {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'materials'), $post_id);
        
        // Handle both ACF taxonomy field format and legacy string format
        if (is_array($value)) {
            // ACF taxonomy field returns term objects or IDs
            if (!empty($value) && is_object($value[0])) {
                return $value; // Already term objects
            } elseif (!empty($value) && is_numeric($value[0])) {
                // Term IDs - convert to term objects
                return get_terms([
                    'taxonomy' => 'construction_material',
                    'include' => $value,
                    'hide_empty' => false,
                ]);
            }
        } elseif (is_string($value) && !empty($value)) {
            // Legacy string format - try to find matching terms
            $material_names = array_map('trim', explode(',', $value));
            $terms = [];
            foreach ($material_names as $name) {
                $term = get_term_by('name', $name, 'construction_material');
                if ($term) {
                    $terms[] = $term;
                }
            }
            return $terms;
        }
        
        return [];
    }

    /**
     * Get material IDs for a realizace post
     *
     * @param int $post_id Post ID
     * @return array Array of term IDs
     */
    public static function getMaterialIds(int $post_id): array {
        $materials = self::getMaterialsArray($post_id);
        return array_map(fn($term) => $term->term_id, $materials);
    }

    /**
     * Get field selector constants for use in other classes
     */
    public static function getPointsFieldSelector(): string {
        return DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'points');
    }

    public static function getRejectionReasonFieldSelector(): string {
        return DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'rejection_reason');
    }

    public static function getGalleryFieldSelector(): string {
        return DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'gallery');
    }

    public static function getAreaFieldSelector(): string {
        return DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'area');
    }

    public static function getConstructionTypeFieldSelector(): string {
        return DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'construction_type');
    }

    public static function getMaterialsFieldSelector(): string {
        return DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'materials');
    }

    public static function getAwardedPointsFieldSelector(): string {
        return DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'awarded_points_meta');
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
            'construction_type' => self::getConstructionType($post_id), // Legacy string format
            'construction_types' => self::getConstructionTypes($post_id), // New taxonomy format
            'construction_type_ids' => self::getConstructionTypeIds($post_id),
            'materials' => self::getMaterials($post_id), // Legacy string format
            'materials_array' => self::getMaterialsArray($post_id), // New taxonomy format
            'material_ids' => self::getMaterialIds($post_id),
        ];
    }
}
