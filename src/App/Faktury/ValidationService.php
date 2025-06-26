<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Services\DebugLogger;

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

    public function __construct(\WP_Post $post_being_validated) {
        $this->post = $post_being_validated;
        $this->user_id = (int) $post_being_validated->post_author;
    }

    /**
     * Basic validation - always returns true for now
     * TODO: Implement actual business rules
     *
     * @return bool
     */
    public function isValid(): bool {
        DebugLogger::log('--- Starting STREAMLINED isValid() Check ---', ['post_id' => $this->post->ID]);

        // Only validate the rules that ACF cannot handle: the date and monthly value limits.
        $check1 = $this->isDateValid();
        DebugLogger::log('Check 1: isDateValid()', ['result' => $check1]);

        $check2 = $this->isMonthlyLimitOk();
        DebugLogger::log('Check 2: isMonthlyLimitOk()', ['result' => $check2]);

        $final_result = $check1 && $check2;
        DebugLogger::log('--- Final STREAMLINED isValid() Result ---', ['result' => $final_result]);

        return $final_result;
    }


    /**
     * Validate invoice date is in current year
     *
     * @return bool
     */
    public function isDateValid(): bool {
        $invoice_date_str = FakturaFieldService::getInvoiceDate($this->post->ID);
        $current_year = date('Y');
        
        DebugLogger::log('isDateValid() - Date validation:', [
            'invoice_date_str' => $invoice_date_str,
            'current_year' => $current_year,
            'is_empty' => empty($invoice_date_str)
        ]);
        
        // If no date is set yet, that's OK (admin will fill it)
        if (empty($invoice_date_str)) {
            DebugLogger::log('isDateValid() - No date set, returning true');
            return true;
        }
        
        $invoice_year = '';
        
        // ACF date picker returns Ymd format (20250625)
        if (strlen($invoice_date_str) === 8) {
            $invoice_year = substr($invoice_date_str, 0, 4);
            DebugLogger::log('isDateValid() - Ymd format detected', ['invoice_year' => $invoice_year]);
        } else {
            // Try to parse other date formats
            $date = \DateTime::createFromFormat('Y-m-d', $invoice_date_str);
            if (!$date) {
                DebugLogger::log('isDateValid() - Invalid date format, returning false');
                return false; // Invalid date format
            }
            $invoice_year = $date->format('Y');
            DebugLogger::log('isDateValid() - Y-m-d format detected', ['invoice_year' => $invoice_year]);
        }
        
        $is_valid = $invoice_year === $current_year;
        DebugLogger::log('isDateValid() - Final result:', [
            'invoice_year' => $invoice_year,
            'current_year' => $current_year,
            'is_valid' => $is_valid
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
        
        DebugLogger::log('isInvoiceNumberUnique() - Starting check:', [
            'invoice_number' => $invoice_number,
            'is_empty' => empty($invoice_number),
            'user_id' => $this->user_id
        ]);
        
        // If no invoice number is set yet, that's OK (admin will fill it)
        if (empty($invoice_number)) {
            DebugLogger::log('isInvoiceNumberUnique() - No invoice number set, returning true');
            return true;
        }
        
        // Check for existing invoices with same number for this user
        $args = [
            'post_type' => 'invoice',
            'post_status' => ['publish', 'pending', 'rejected'], // Check all statuses
            'author' => $this->user_id,
            'meta_key' => FakturaFieldService::getInvoiceNumberFieldSelector(),
            'meta_value' => $invoice_number,
            'exclude' => [$this->post->ID], // Exclude current post
            'fields' => 'ids',
            'posts_per_page' => 1 // We only need to know if any exist
        ];
        
        DebugLogger::log('isInvoiceNumberUnique() - Query args:', $args);
        
        $query = new \WP_Query($args);
        
        DebugLogger::log('isInvoiceNumberUnique() - Query results:', [
            'post_count' => $query->post_count,
            'found_posts' => $query->found_posts,
            'is_unique' => $query->post_count === 0
        ]);
        
        // Return true if no duplicates found
        return $query->post_count === 0;
    }
    */

    /**
     * Check monthly limit (300,000 CZK per user per month)
     * 
     * CRITICAL: Checks against invoice_date ACF field, not post creation date
     *
     * @return bool
     */
    public function isMonthlyLimitOk(): bool {
        global $wpdb;
        $limit = 300000;
        $current_value = FakturaFieldService::getValue($this->post->ID);
        $invoice_date_str = FakturaFieldService::getInvoiceDate($this->post->ID);

        DebugLogger::log('isMonthlyLimitOk() - Starting check:', [
            'limit' => $limit,
            'current_value' => $current_value,
            'invoice_date_str' => $invoice_date_str,
            'user_id' => $this->user_id,
            'post_id' => $this->post->ID
        ]);

        // If no invoice date is set, we cannot check the limit. Assume it's ok for now.
        // This will be caught by the required fields check later.
        if (empty($invoice_date_str)) {
            DebugLogger::log('isMonthlyLimitOk() - No invoice date set, returning true');
            return true;
        }

        // The date is stored as 'Ymd'. We need the year and month.
        $year = substr($invoice_date_str, 0, 4);
        $month = substr($invoice_date_str, 4, 2);

        // Date range for the query (e.g., '20250601' to '20250631')
        $start_date = $year . $month . '01';
        $end_date = $year . $month . '31';

        DebugLogger::log('isMonthlyLimitOk() - Date parsing:', [
            'year' => $year,
            'month' => $month,
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);

        $value_meta_key = FakturaFieldService::getValueFieldSelector();
        $date_meta_key = FakturaFieldService::getInvoiceDateFieldSelector();

        DebugLogger::log('isMonthlyLimitOk() - Meta keys:', [
            'value_meta_key' => $value_meta_key,
            'date_meta_key' => $date_meta_key
        ]);

        $total_approved_this_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(value_meta.meta_value)
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} date_meta ON p.ID = date_meta.post_id AND date_meta.meta_key = %s
            JOIN {$wpdb->postmeta} value_meta ON p.ID = value_meta.post_id AND value_meta.meta_key = %s
            WHERE p.post_type = 'invoice'
            AND p.post_author = %d
            AND p.post_status = 'publish'
            AND p.ID != %d
            AND (date_meta.meta_value BETWEEN %s AND %s)",
            $date_meta_key,
            $value_meta_key,
            $this->user_id,
            $this->post->ID, // Exclude the current post from the sum
            $start_date,
            $end_date
        ));

        $total_with_current = $total_approved_this_month + $current_value;
        $is_within_limit = $total_with_current <= $limit;

        DebugLogger::log('isMonthlyLimitOk() - Final calculation:', [
            'total_approved_this_month' => $total_approved_this_month,
            'current_value' => $current_value,
            'total_with_current' => $total_with_current,
            'limit' => $limit,
            'is_within_limit' => $is_within_limit
        ]);

        return $is_within_limit;
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
            $current_value = FakturaFieldService::getValue($this->post->ID);
            return sprintf(
                'Měsíční limit 300 000 Kč byl překročen. Tato faktura (%s Kč) by překročila povolenou částku.',
                number_format($current_value, 0, ',', ' ')
            );
        }

        return 'Faktura je neplatná z neznámého důvodu.';
    }
}