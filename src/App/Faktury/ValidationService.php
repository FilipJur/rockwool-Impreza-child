<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Services\DebugLogger;
use MistrFachman\Services\DomainConfigurationService;

/**
 * Faktury Validation Service - Business Rules Validation
 *
 * Isolates complex database queries and business logic validation.
 * Keeps AdminController lean by centralizing validation logic.
 * 
 * TODO: Implement full validation rules:
 * - Monthly limit checks (300,000 CZK per user per month)
 * - Invoice number uniqueness per user
 * - Date validation (current year only)
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ValidationService {
    private int $user_id;
    private \WP_Post $post;
    private array $post_data;

    public function __construct(\WP_Post $post_being_validated, ?array $post_data = null) {
        $this->post = $post_being_validated;
        $this->post_data = $post_data ?? $_POST; // Use passed data or default to global
        $this->user_id = (int) $post_being_validated->post_author;
    }

    /**
     * Simple validation check for publishing
     */
    public function isPublishable(): bool {
        $date_valid = $this->isDateValid();
        $limit_ok = $this->isMonthlyLimitOk();
        
        $result = $date_valid && $limit_ok;
        
        DebugLogger::logValidation('invoice', $this->post->ID, [
            'date_valid' => $date_valid,
            'monthly_limit_ok' => $limit_ok,
            'publishable' => $result,
            'user_id' => $this->user_id,
            'source' => 'ValidationService::isPublishable'
        ]);
        
        return $result;
    }


    /**
     * Simple date validation - current year only
     */
    public function isDateValid(): bool {
        $invoice_date_str = $this->get_fresh_value('invoiceDate');
        
        if (empty($invoice_date_str)) {
            DebugLogger::log('Date validation: empty date, allowing');
            return true;
        }
        
        $invoice_year = substr($invoice_date_str, 0, 4);
        $current_year = date('Y');
        $is_valid = $invoice_year === $current_year;
        
        DebugLogger::log('Date validation:', [
            'date' => $invoice_date_str,
            'year' => $invoice_year,
            'current_year' => $current_year,
            'valid' => $is_valid
        ]);
        
        return $is_valid;
    }
    
    /**
     * Check if invoice number is unique for this user
     *
     * @return bool
     */
    /* // Disabling for development to allow generic invoice numbers.
    public function isInvoiceNumberUnique(): bool {
        $invoice_number = FakturaFieldService::getInvoiceNumber($this->post->ID);
        
        // If no invoice number is set yet, that's OK (admin will fill it)
        if (empty($invoice_number)) {
            return true;
        }
        
        // Check for existing invoices with same number for this user
        $args = [
            'post_type' => DomainConfigurationService::getWordPressPostType('invoice'),
            'post_status' => ['publish', 'pending', 'rejected'], // Check all statuses
            'author' => $this->user_id,
            'meta_key' => FakturaFieldService::getInvoiceNumberFieldSelector(),
            'meta_value' => $invoice_number,
            'exclude' => [$this->post->ID], // Exclude current post
            'fields' => 'ids',
            'posts_per_page' => 1 // We only need to know if any exist
        ];
        
        $query = new \WP_Query($args);
        
        // Return true if no duplicates found
        return $query->post_count === 0;
    }
    */

    /**
     * Get fresh value - prioritize $_POST ACF data, fallback to database
     */
    private function get_fresh_value(string $field_name) {
        DebugLogger::log("get_fresh_value called for '{$field_name}'");
        
        // Try to get from $_POST ACF data first
        if (isset($this->post_data['acf']) && is_array($this->post_data['acf'])) {
            $field_key = match($field_name) {
                'value' => FakturaFieldService::getValueFieldSelector(true),
                'invoiceDate' => FakturaFieldService::getInvoiceDateFieldSelector(true),
                default => null
            };
            
            DebugLogger::log("Looking for ACF field key: '{$field_key}'", [
                'available_acf_keys' => array_keys($this->post_data['acf'])
            ]);
            
            if ($field_key && isset($this->post_data['acf'][$field_key])) {
                $value = $this->post_data['acf'][$field_key];
                DebugLogger::log("-> Found in ACF POST data:", ['field_key' => $field_key, 'value' => $value]);
                return $value;
            }
        }
        
        // Fallback to database values
        $db_value = match($field_name) {
            'value' => FakturaFieldService::getValue($this->post->ID),
            'invoiceDate' => FakturaFieldService::getInvoiceDate($this->post->ID),
            default => null
        };
        
        DebugLogger::log("-> Using DB value:", ['field_name' => $field_name, 'db_value' => $db_value]);
        return $db_value;
    }

    /**
     * Simple monthly limit check
     */
    public function isMonthlyLimitOk(): bool {
        $current_value = (int) $this->get_fresh_value('value');
        $invoice_date_str = $this->get_fresh_value('invoiceDate');

        if (empty($invoice_date_str)) {
            DebugLogger::log('Monthly limit: no date, allowing');
            return true;
        }

        $total_from_db = $this->get_total_for_month($this->user_id, $invoice_date_str, $this->post->ID);
        
        $limit = 300000;
        $total_with_current = $total_from_db + $current_value;
        $is_within_limit = $total_with_current <= $limit;
        
        DebugLogger::log('Monthly limit check:', [
            'current_value' => $current_value,
            'total_from_db' => $total_from_db,
            'total' => $total_with_current,
            'limit' => $limit,
            'within_limit' => $is_within_limit
        ]);
        
        return $is_within_limit;
    }

    /**
     * Get the total approved and pending invoice value for a given month for a specific user
     * Used by monthly limit validation to prevent race conditions
     * 
     * CRITICAL: Includes both 'publish' and 'pending' status to prevent concurrent approval bypass
     */
    private function get_total_for_month(int $user_id, string $invoice_date_str, int $exclude_post_id): int {
        global $wpdb;
        
        $year = substr($invoice_date_str, 0, 4);
        $month = substr($invoice_date_str, 4, 2);
        $start_date = $year . $month . '01';
        $end_date = $year . $month . '31';

        $value_meta_key = FakturaFieldService::getValueFieldSelector();
        $date_meta_key = FakturaFieldService::getInvoiceDateFieldSelector();

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(value_meta.meta_value) FROM {$wpdb->posts} p " .
            "JOIN {$wpdb->postmeta} date_meta ON p.ID = date_meta.post_id AND date_meta.meta_key = %s " .
            "JOIN {$wpdb->postmeta} value_meta ON p.ID = value_meta.post_id AND value_meta.meta_key = %s " .
            "WHERE p.post_type = %s AND p.post_author = %d AND p.post_status IN ('publish', 'pending') AND p.ID != %d " .
            "AND (date_meta.meta_value BETWEEN %s AND %s)",
            $date_meta_key, $value_meta_key, DomainConfigurationService::getWordPressPostType('invoice'), $user_id, $exclude_post_id, $start_date, $end_date
        ));
    }

    /**
     * Get validation error message for failed validation
     *
     * @return string
     */
    public function getValidationMessage(): string {
        if (!$this->isDateValid()) {
            return 'Datum faktury musí být z aktuálního roku.';
        }

        if (!$this->isMonthlyLimitOk()) {
            $current_value = (int) $this->get_fresh_value('value');
            $invoice_date_str = $this->get_fresh_value('invoiceDate');
            $total_from_db = $this->get_total_for_month($this->user_id, $invoice_date_str, $this->post->ID);
            $limit = 300000;
            $total_with_current = $total_from_db + $current_value;
            $exceeded_by = $total_with_current - $limit;
            
            return sprintf(
                'Měsíční limit 300 000 Kč byl překročen. Tato faktura (%s Kč) by překročila povolenou částku o %s Kč. Aktuálně nahráno: %s Kč.',
                number_format($current_value, 0, ',', ' '),
                number_format($exceeded_by, 0, ',', ' '),
                number_format($total_from_db, 0, ',', ' ')
            );
        }

        return 'Faktura je neplatná z neznámého důvodu.';
    }
}