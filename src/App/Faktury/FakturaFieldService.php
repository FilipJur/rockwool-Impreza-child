<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\FieldServiceBase;
use MistrFachman\Services\DomainConfigurationService;

/**
 * Faktura Field Service - Centralized Field Access
 *
 * Provides a single source of truth for all faktura field access.
 * Eliminates hardcoded field names and ensures consistent field access patterns.
 * Mirrors RealizaceFieldService pattern exactly.
 *
 * Usage:
 * - FakturaFieldService::getPoints($post_id)
 * - FakturaFieldService::getRejectionReason($post_id)
 * - FakturaFieldService::setValue($post_id, $value)
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FakturaFieldService extends FieldServiceBase {

    /**
     * Domain key for configuration access
     */
    private const DOMAIN_KEY = 'invoice';


    /**
     * Get points value for a faktura post
     *
     * @param int $post_id Post ID
     * @return int Points value
     */
    public static function getPoints(int $post_id): int {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'points'), $post_id);
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * Set points value for a faktura post
     *
     * @param int $post_id Post ID
     * @param int $points Points to set
     * @return bool Success status
     */
    public static function setPoints(int $post_id, int $points): bool {
        return self::setFieldValueInternal(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'points'), $post_id, $points);
    }

    /**
     * Get rejection reason for a faktura post
     *
     * @param int $post_id Post ID
     * @return string Rejection reason
     */
    public static function getRejectionReason(int $post_id): string {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'rejection_reason'), $post_id);
        return is_string($value) ? $value : '';
    }

    /**
     * Set rejection reason for a faktura post
     *
     * @param int $post_id Post ID
     * @param string $reason Rejection reason
     * @return bool Success status
     */
    public static function setRejectionReason(int $post_id, string $reason): bool {
        return self::setFieldValueInternal(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'rejection_reason'), $post_id, $reason);
    }

    /**
     * Get invoice value for a faktura post
     *
     * @param int $post_id Post ID
     * @return int Invoice value in CZK
     */
    public static function getValue(int $post_id): int {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'value'), $post_id);
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * Set invoice value for a faktura post
     *
     * @param int $post_id Post ID
     * @param int $value Invoice value in CZK
     * @return bool Success status
     */
    public static function setValue(int $post_id, int $value): bool {
        return self::setFieldValueInternal(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'value'), $post_id, $value);
    }

    /**
     * Get invoice file for a faktura post
     *
     * @param int $post_id Post ID
     * @return array|null File attachment data
     */
    public static function getFile(int $post_id): ?array {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'file'), $post_id);
        return is_array($value) ? $value : null;
    }

    /**
     * Set invoice file for a faktura post
     *
     * @param int $post_id Post ID
     * @param int $attachment_id Attachment ID
     * @return bool Success status
     */
    public static function setFile(int $post_id, int $attachment_id): bool {
        return self::setFieldValueInternal(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'file'), $post_id, $attachment_id);
    }

    /**
     * Get invoice number (admin field) for a faktura post
     *
     * @param int $post_id Post ID
     * @return string Invoice number
     */
    public static function getInvoiceNumber(int $post_id): string {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'invoice_number'), $post_id);
        return is_string($value) ? $value : '';
    }

    /**
     * Set invoice number (admin field) for a faktura post
     *
     * @param int $post_id Post ID
     * @param string $number Invoice number
     * @return bool Success status
     */
    public static function setInvoiceNumber(int $post_id, string $number): bool {
        return self::setFieldValueInternal(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'invoice_number'), $post_id, $number);
    }

    /**
     * Get invoice date (admin field) for a faktura post
     *
     * @param int $post_id Post ID
     * @return string Invoice date
     */
    public static function getInvoiceDate(int $post_id): string {
        $value = self::getFieldValue(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'invoice_date'), $post_id);
        return is_string($value) ? $value : '';
    }

    /**
     * Set invoice date (admin field) for a faktura post
     *
     * @param int $post_id Post ID
     * @param string $date Invoice date
     * @return bool Success status
     */
    public static function setInvoiceDate(int $post_id, string $date): bool {
        return self::setFieldValueInternal(DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'invoice_date'), $post_id, $date);
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

    public static function getValueFieldSelector(bool $get_key = false): string {
        return $get_key ? DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'value_field_key') : DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'value');
    }

    public static function getFileFieldSelector(): string {
        return DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'file');
    }

    public static function getInvoiceNumberFieldSelector(): string {
        return DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'invoice_number');
    }

    public static function getInvoiceDateFieldSelector(bool $get_key = false): string {
        return $get_key ? DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'invoice_date_field_key') : DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'invoice_date');
    }


    /**
     * Get all faktura data for a post in one call
     *
     * @param int $post_id Post ID
     * @return array Complete faktura data
     */
    public static function getAllFields(int $post_id): array {
        return [
            'points' => self::getPoints($post_id),
            'rejection_reason' => self::getRejectionReason($post_id),
            'value' => self::getValue($post_id),
            'file' => self::getFile($post_id),
            'invoice_number' => self::getInvoiceNumber($post_id),
            'invoice_date' => self::getInvoiceDate($post_id),
        ];
    }

    /**
     * Get the meta field name for tracking awarded points
     * Used to prevent duplicate point awarding
     *
     * @return string Meta field name
     */
    public static function getAwardedPointsMetaFieldName(): string {
        return DomainConfigurationService::getFieldName(self::DOMAIN_KEY, 'awarded_points_meta');
    }
}
