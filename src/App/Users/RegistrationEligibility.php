<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Registration Eligibility - Handles user eligibility checks for registration
 *
 * Comprehensive security checks to prevent various edge cases and attacks
 * during the registration process.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RegistrationEligibility {

    public function __construct(
        private RoleManager $role_manager,
        private BusinessDataManager $business_manager
    ) {}

    /**
     * Check if user is eligible for registration submission
     *
     * Comprehensive security check to prevent various edge cases and attacks.
     *
     * @param int $user_id User ID to check
     * @return array{eligible: bool, reason: string, message: string} Eligibility result
     */
    public function check_user_registration_eligibility(int $user_id): array
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return [
                'eligible' => false,
                'reason' => 'user_not_found',
                'message' => 'Uživatelský účet nebyl nalezen.'
            ];
        }

        // Check if user has pending approval role
        if (!$this->role_manager->is_pending_user($user_id)) {
            // Determine specific message based on current role
            if ($this->role_manager->is_full_member($user_id)) {
                return [
                    'eligible' => false,
                    'reason' => 'already_approved',
                    'message' => 'Váš účet je již schválen. Nemusíte vyplňovat registrační formulář znovu.'
                ];
            }

            return [
                'eligible' => false,
                'reason' => 'invalid_role',
                'message' => 'Váš účet nemá správné oprávnění pro registraci. Kontaktujte administrátora.'
            ];
        }

        // Check current registration status
        $current_status = $this->role_manager->get_user_status($user_id);

        if ($current_status === RegistrationStatus::AWAITING_REVIEW) {
            return [
                'eligible' => false,
                'reason' => 'already_submitted',
                'message' => 'Registrační formulář byl již odeslán a čeká na schválení. Nemusíte jej vyplňovat znovu.'
            ];
        }

        if ($current_status === RegistrationStatus::APPROVED) {
            return [
                'eligible' => false,
                'reason' => 'already_approved_status',
                'message' => 'Vaše registrace je již schválena.'
            ];
        }

        // Check if user already has business data (potential duplicate submission)
        if ($this->business_manager->has_business_data($user_id)) {
            return [
                'eligible' => false,
                'reason' => 'duplicate_business_data',
                'message' => 'Firemní údaje již byly odeslány. Nemusíte formulář vyplňovat znovu.'
            ];
        }

        // All checks passed
        return [
            'eligible' => true,
            'reason' => 'eligible',
            'message' => 'Uživatel může pokračovat s registrací.'
        ];
    }
}