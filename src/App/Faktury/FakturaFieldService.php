<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\FieldServiceBase;

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
     * Field selector constants - Single source of truth
     * Following Realizace pattern with domain-specific naming
     * Uses full group_field pattern to match Realizace architecture
     */
    private const POINTS_FIELD = 'admin_management_invoice_points_assigned';
    private const REJECTION_REASON_FIELD = 'admin_management_invoice_rejection_reason';
    private const VALUE_FIELD = 'invoice_value';
    private const FILE_FIELD = 'invoice_file';
    private const INVOICE_NUMBER_ADMIN_FIELD = 'invoice_number';
    private const INVOICE_DATE_ADMIN_FIELD = 'invoice_date';

    /**
     * Get points value for a faktura post
     *
     * @param int $post_id Post ID
     * @return int Points value
     */
    public static function getPoints(int $post_id): int {
        $value = self::getFieldValue(self::POINTS_FIELD, $post_id);
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
        return self::setFieldValueInternal(self::POINTS_FIELD, $post_id, $points);
    }

    /**
     * Get rejection reason for a faktura post
     *
     * @param int $post_id Post ID
     * @return string Rejection reason
     */
    public static function getRejectionReason(int $post_id): string {
        $value = self::getFieldValue(self::REJECTION_REASON_FIELD, $post_id);
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
        return self::setFieldValueInternal(self::REJECTION_REASON_FIELD, $post_id, $reason);
    }

    /**
     * Get invoice value for a faktura post
     *
     * @param int $post_id Post ID
     * @return int Invoice value in CZK
     */
    public static function getValue(int $post_id): int {
        $value = self::getFieldValue(self::VALUE_FIELD, $post_id);
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
        return self::setFieldValueInternal(self::VALUE_FIELD, $post_id, $value);
    }

    /**
     * Get invoice file for a faktura post
     *
     * @param int $post_id Post ID
     * @return array|null File attachment data
     */
    public static function getFile(int $post_id): ?array {
        $value = self::getFieldValue(self::FILE_FIELD, $post_id);
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
        return self::setFieldValueInternal(self::FILE_FIELD, $post_id, $attachment_id);
    }

    /**
     * Get invoice number (admin field) for a faktura post
     *
     * @param int $post_id Post ID
     * @return string Invoice number
     */
    public static function getInvoiceNumber(int $post_id): string {
        $value = self::getFieldValue(self::INVOICE_NUMBER_ADMIN_FIELD, $post_id);
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
        return self::setFieldValueInternal(self::INVOICE_NUMBER_ADMIN_FIELD, $post_id, $number);
    }

    /**
     * Get invoice date (admin field) for a faktura post
     *
     * @param int $post_id Post ID
     * @return string Invoice date
     */
    public static function getInvoiceDate(int $post_id): string {
        $value = self::getFieldValue(self::INVOICE_DATE_ADMIN_FIELD, $post_id);
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
        return self::setFieldValueInternal(self::INVOICE_DATE_ADMIN_FIELD, $post_id, $date);
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

    public static function getValueFieldSelector(): string {
        return self::VALUE_FIELD;
    }

    public static function getFileFieldSelector(): string {
        return self::FILE_FIELD;
    }

    public static function getInvoiceNumberFieldSelector(): string {
        return self::INVOICE_NUMBER_ADMIN_FIELD;
    }

    public static function getInvoiceDateFieldSelector(): string {
        return self::INVOICE_DATE_ADMIN_FIELD;
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
}
