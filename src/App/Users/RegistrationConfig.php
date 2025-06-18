<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Registration Configuration
 *
 * Single source of truth for registration form configuration
 * and related constants. Centralizes all form-related settings.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RegistrationConfig {

    /**
     * Contact Form 7 ID for final registration form
     */
    public const FINAL_REGISTRATION_FORM_ID = 292;

    /**
     * Expected form title for validation
     */
    public const FORM_TITLE = 'Final registrace';

    /**
     * Phone number pattern for OTP registration detection
     */
    public const PHONE_PATTERN = '/^\d+(-\d+)?$/';

    /**
     * Email field names to search in form data
     */
    public const EMAIL_FIELDS = [
        'email',
        'e-mail', 
        'email_address',
        'your-email'
    ];

    /**
     * Phone field names to search in form data
     */
    public const PHONE_FIELDS = [
        'phone',
        'telefon',
        'tel',
        'phone_number',
        'mobile'
    ];

    /**
     * Get the configured registration form ID
     *
     * @return int Form ID
     */
    public static function getFormId(): int {
        return self::FINAL_REGISTRATION_FORM_ID;
    }

    /**
     * Check if a Contact Form 7 form is the registration form
     *
     * @param \WPCF7_ContactForm $form Contact Form 7 instance
     * @return bool True if this is the registration form
     */
    public static function isRegistrationForm(\WPCF7_ContactForm $form): bool {
        $submitted_id = $form->id();
        $expected_id = self::getFormId();
        
        // Handle both string and int IDs for flexibility
        return ($submitted_id == $expected_id) || 
               (is_string($expected_id) && $submitted_id === $expected_id) ||
               (is_int($expected_id) && (int)$submitted_id === $expected_id);
    }

    /**
     * Check if a username indicates phone number registration
     *
     * @param string $username Username to check
     * @return bool True if username appears to be a phone number
     */
    public static function isPhoneRegistration(string $username): bool {
        return is_numeric($username) || preg_match(self::PHONE_PATTERN, $username);
    }

    /**
     * Get email field names for form data search
     *
     * @return array<string> Email field names
     */
    public static function getEmailFields(): array {
        return self::EMAIL_FIELDS;
    }

    /**
     * Get phone field names for form data search
     *
     * @return array<string> Phone field names
     */
    public static function getPhoneFields(): array {
        return self::PHONE_FIELDS;
    }

    /**
     * Validate form configuration
     *
     * @return bool True if configuration is valid
     */
    public static function isConfigurationValid(): bool {
        return self::getFormId() > 0 && 
               !empty(self::FORM_TITLE) &&
               !empty(self::EMAIL_FIELDS) &&
               !empty(self::PHONE_FIELDS);
    }
}