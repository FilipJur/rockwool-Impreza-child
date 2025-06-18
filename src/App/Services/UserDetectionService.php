<?php

declare(strict_types=1);

namespace MistrFachman\Services;

use MistrFachman\Users\RoleManager;
use MistrFachman\Users\RegistrationConfig;

/**
 * User Detection Service
 *
 * Handles various methods of detecting the current user during
 * form submissions, especially when AJAX requests lose session context.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UserDetectionService {

    public function __construct(
        private RoleManager $role_manager
    ) {}

    /**
     * Detect user using multiple fallback methods
     *
     * Tries session, cookie, and form data in order of reliability.
     *
     * @param array $form_data Optional form data for email-based detection
     * @return int|null User ID if detected, null otherwise
     */
    public function detectUser(array $form_data = []): ?int {
        // Try standard WordPress session first
        $user_id = $this->detectUserFromSession();
        if ($user_id) {
            mycred_debug('User detected via session', [
                'user_id' => $user_id,
                'method' => 'session'
            ], 'user_detection', 'info');
            return $user_id;
        }

        // Try cookie fallback for AJAX issues
        $user_id = $this->detectUserFromCookie();
        if ($user_id) {
            mycred_debug('User detected via cookie fallback', [
                'user_id' => $user_id,
                'method' => 'cookie'
            ], 'user_detection', 'info');
            return $user_id;
        }

        // Last resort: try form data
        if (!empty($form_data)) {
            $user_id = $this->detectUserFromFormData($form_data);
            if ($user_id) {
                mycred_debug('User detected via form data fallback', [
                    'user_id' => $user_id,
                    'method' => 'form_data'
                ], 'user_detection', 'info');
                return $user_id;
            }
        }

        mycred_debug('User detection failed - no valid user found', [
            'session_user_id' => get_current_user_id(),
            'has_cookie' => isset($_COOKIE[LOGGED_IN_COOKIE]),
            'form_data_available' => !empty($form_data)
        ], 'user_detection', 'warning');

        return null;
    }

    /**
     * Detect user from WordPress session
     *
     * @return int|null User ID if logged in, null otherwise
     */
    public function detectUserFromSession(): ?int {
        $user_id = get_current_user_id();
        return $user_id > 0 ? $user_id : null;
    }

    /**
     * Detect user from WordPress login cookie
     *
     * Handles cases where AJAX requests lose session context.
     *
     * @return int|null User ID if found in cookie, null otherwise
     */
    public function detectUserFromCookie(): ?int {
        if (!isset($_COOKIE[LOGGED_IN_COOKIE])) {
            return null;
        }

        $cookie_elements = wp_parse_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in');
        if (!$cookie_elements || !isset($cookie_elements['username'])) {
            return null;
        }

        $user = get_user_by('login', $cookie_elements['username']);
        if (!$user) {
            return null;
        }

        mycred_debug('Cookie authentication successful', [
            'username' => $cookie_elements['username'],
            'user_id' => $user->ID
        ], 'user_detection', 'info');

        return $user->ID;
    }

    /**
     * Detect user from form submission data
     *
     * Attempts to match user by email or phone number from form fields.
     * Only returns users with pending_approval role for security.
     *
     * @param array $form_data Form submission data
     * @return int|null User ID if found and pending, null otherwise
     */
    public function detectUserFromFormData(array $form_data): ?int {
        if (empty($form_data)) {
            return null;
        }

        // Try email-based detection first
        $user_id = $this->detectUserByEmail($form_data);
        if ($user_id) {
            return $user_id;
        }

        // Try phone-based detection
        $user_id = $this->detectUserByPhone($form_data);
        if ($user_id) {
            return $user_id;
        }

        return null;
    }

    /**
     * Detect user by email from form data
     *
     * @param array $form_data Form submission data
     * @return int|null User ID if found and pending, null otherwise
     */
    private function detectUserByEmail(array $form_data): ?int {
        foreach (RegistrationConfig::getEmailFields() as $field) {
            if (empty($form_data[$field])) {
                continue;
            }

            $email = sanitize_email($form_data[$field]);
            if (!is_email($email)) {
                continue;
            }

            $user = get_user_by('email', $email);
            if (!$user) {
                continue;
            }

            // Only return pending users for security
            if ($this->role_manager->is_pending_user($user->ID)) {
                mycred_debug('User found by email', [
                    'email' => $email,
                    'user_id' => $user->ID,
                    'field_name' => $field,
                    'is_pending' => true
                ], 'user_detection', 'info');
                return $user->ID;
            }

            mycred_debug('User found by email but not pending', [
                'email' => $email,
                'user_id' => $user->ID,
                'user_roles' => $user->roles,
                'field_name' => $field
            ], 'user_detection', 'warning');
        }

        return null;
    }

    /**
     * Detect user by phone number from form data
     *
     * @param array $form_data Form submission data
     * @return int|null User ID if found and pending, null otherwise
     */
    private function detectUserByPhone(array $form_data): ?int {
        foreach (RegistrationConfig::getPhoneFields() as $field) {
            if (empty($form_data[$field])) {
                continue;
            }

            $phone = sanitize_text_field($form_data[$field]);
            $phone_variations = $this->generatePhoneVariations($phone);

            foreach ($phone_variations as $phone_variant) {
                $user = get_user_by('login', $phone_variant);
                if (!$user) {
                    continue;
                }

                // Only return pending users for security
                if ($this->role_manager->is_pending_user($user->ID)) {
                    mycred_debug('User found by phone', [
                        'original_phone' => $phone,
                        'matched_phone' => $phone_variant,
                        'user_id' => $user->ID,
                        'field_name' => $field,
                        'is_pending' => true
                    ], 'user_detection', 'info');
                    return $user->ID;
                }
            }
        }

        return null;
    }

    /**
     * Generate phone number variations for matching
     *
     * @param string $phone Original phone number
     * @return array<string> Phone variations to try
     */
    private function generatePhoneVariations(string $phone): array {
        $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
        
        return [
            $phone,                                    // Original format
            $clean_phone,                             // Cleaned format
            ltrim($clean_phone, '+420'),              // Without country code
            '+420' . ltrim($clean_phone, '+420')      // With country code
        ];
    }

    /**
     * Get debug information about current detection context
     *
     * @return array Debug information
     */
    public function getDetectionContext(): array {
        return [
            'session_user_id' => get_current_user_id(),
            'is_user_logged_in' => is_user_logged_in(),
            'has_logged_in_cookie' => isset($_COOKIE[LOGGED_IN_COOKIE]),
            'cookie_count' => count($_COOKIE),
            'is_ajax' => wp_doing_ajax(),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
    }
}