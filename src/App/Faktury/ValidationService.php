<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

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
        // Check all validation rules
        return $this->hasRequiredFields() 
            && $this->isMonthlyLimitOk() 
            && $this->isDateValid() 
            && $this->isInvoiceNumberUnique();
    }

    /**
     * Check if required fields are present
     *
     * @return bool
     */
    public function hasRequiredFields(): bool {
        $invoice_value = FakturaFieldService::getValue($this->post->ID);
        $invoice_file = FakturaFieldService::getFile($this->post->ID);
        
        return $invoice_value > 0 && !empty($invoice_file);
    }

    /**
     * Validate invoice date is in current year
     *
     * @return bool
     */
    public function isDateValid(): bool {
        $invoice_date_str = FakturaFieldService::getInvoiceDate($this->post->ID);
        
        // If no date is set yet, that's OK (admin will fill it)
        if (empty($invoice_date_str)) {
            return true;
        }
        
        // ACF date picker returns Ymd format (20250625)
        if (strlen($invoice_date_str) === 8) {
            $invoice_year = substr($invoice_date_str, 0, 4);
        } else {
            // Try to parse other date formats
            $date = \DateTime::createFromFormat('Y-m-d', $invoice_date_str);
            if (!$date) {
                return false; // Invalid date format
            }
            $invoice_year = $date->format('Y');
        }
        
        $current_year = date('Y');
        
        return $invoice_year === $current_year;
    }
    
    /**
     * Check if invoice number is unique for this user
     *
     * @return bool
     */
    public function isInvoiceNumberUnique(): bool {
        $invoice_number = FakturaFieldService::getInvoiceNumber($this->post->ID);
        
        // If no invoice number is set yet, that's OK (admin will fill it)
        if (empty($invoice_number)) {
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
        
        $query = new \WP_Query($args);
        
        // Return true if no duplicates found
        return $query->post_count === 0;
    }

    /**
     * Check monthly limit (300,000 CZK per user per month)
     *
     * @return bool
     */
    public function isMonthlyLimitOk(): bool {
        global $wpdb;
        $limit = 300000;
        $current_value = FakturaFieldService::getValue($this->post->ID);
        
        // Get the first day of current month
        $first_of_month = date('Y-m-01 00:00:00');
        
        // Query sum of approved faktury for current month
        $total_approved_this_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(meta.meta_value) 
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} meta ON p.ID = meta.post_id
             WHERE p.post_type = 'invoice'
             AND p.post_author = %d
             AND p.post_status = 'publish'
             AND meta.meta_key = %s
             AND p.post_date >= %s
             AND p.ID != %d",
            $this->user_id,
            FakturaFieldService::getValueFieldSelector(),
            $first_of_month,
            $this->post->ID // Exclude current post to avoid double counting
        ));
        
        // Check if adding current invoice would exceed limit
        return ($total_approved_this_month + $current_value) <= $limit;
    }

    /**
     * Get validation error message for failed validation
     *
     * @return string
     */
    public function getValidationMessage(): string {
        if (!$this->hasRequiredFields()) {
            return 'Faktura musí obsahovat hodnotu a přílohu.';
        }
        
        if (!$this->isMonthlyLimitOk()) {
            $current_value = FakturaFieldService::getValue($this->post->ID);
            return sprintf(
                'Měsíční limit 300 000 Kč byl překročen. Tato faktura (%s Kč) by překročila povolenou částku.',
                number_format($current_value, 0, ',', ' ')
            );
        }
        
        if (!$this->isDateValid()) {
            return 'Datum faktury musí být z aktuálního roku.';
        }
        
        if (!$this->isInvoiceNumberUnique()) {
            $invoice_number = FakturaFieldService::getInvoiceNumber($this->post->ID);
            return sprintf(
                'Číslo faktury "%s" již existuje. Každá faktura musí mít jedinečné číslo.',
                esc_html($invoice_number)
            );
        }
        
        return 'Faktura je neplatná.';
    }
}