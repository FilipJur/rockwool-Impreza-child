<?php

declare(strict_types=1);

namespace MistrFachman\Users;

use MistrFachman\Services\UserDetectionService;

/**
 * Registration Validator - Handles form validation for registration
 *
 * Validates Contact Form 7 registration submissions including email uniqueness,
 * IČO uniqueness, and user eligibility checks.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RegistrationValidator {

    public function __construct(
        private BusinessDataValidator $business_validator,
        private RegistrationEligibility $eligibility_checker,
        private BusinessDataManager $business_manager,
        private UserDetectionService $user_detection_service
    ) {}

    /**
     * Performs a final, fast, server-side validation check upon form submission.
     * This prevents tampering and acts as a security gate. It adheres to the
     * Contact Form 7 filter pattern by returning the validation object.
     *
     * @param \WPCF7_Validation $result The validation object to modify.
     * @param array $tags The form tags.
     * @return \WPCF7_Validation The modified validation object.
     */
    public function validate_registration_form_submission(\WPCF7_Validation $result, array $tags): \WPCF7_Validation
    {
        $contact_form = wpcf7_get_current_contact_form();
        if (!$contact_form || !RegistrationConfig::isRegistrationForm($contact_form)) {
            return $result; // Not our form, return original result immediately.
        }

        // This is the gatekeeper. If this try...catch block passes, the form is valid.
        try {
            $submission = \WPCF7_Submission::get_instance();
            $posted_data = $submission ? $submission->get_posted_data() : [];
            $user_id = $this->user_detection_service->detectUser();

            // Perform dynamic validation FIRST. This will now correctly ignore the optional 'position' field.
            $this->business_validator->validate_form_data($posted_data, $contact_form);

            // Email uniqueness validation - validate specific field
            $this->validate_email_uniqueness($result, $posted_data, $user_id);
            if (!$result->is_valid()) {
                return $result; // Return immediately if email validation failed
            }

            // Other business logic checks can follow...
            $this->validate_user_eligibility($result, $user_id);
            if (!$result->is_valid()) {
                return $result; // Return immediately if eligibility check failed
            }

            // Final IČO uniqueness check - validate specific field
            $this->validate_ico_uniqueness($result, $posted_data, $user_id);
            if (!$result->is_valid()) {
                return $result; // Return immediately if IČO validation failed
            }

        } catch (\Exception $e) {
            $result->invalidate('', $e->getMessage()); // Invalidate the entire form with the error message.
        }

        // IMPORTANT: If all checks pass, return the original (valid) result.
        return $result;
    }

    /**
     * Validate email uniqueness
     *
     * @param \WPCF7_Validation $result Validation result object
     * @param array $posted_data Form data
     * @param int $user_id Current user ID
     */
    private function validate_email_uniqueness(\WPCF7_Validation $result, array $posted_data, int $user_id): void
    {
        $contact_email = sanitize_email($posted_data['contact-email'] ?? '');
        if (!empty($contact_email)) {
            $existing_user_id = email_exists($contact_email);
            if ($existing_user_id && $existing_user_id !== $user_id) {
                $result->invalidate('contact-email', 'Tato e-mailová adresa je již registrována na jiný účet.');
            }
        }
    }

    /**
     * Validate user eligibility
     *
     * @param \WPCF7_Validation $result Validation result object
     * @param int $user_id Current user ID
     */
    private function validate_user_eligibility(\WPCF7_Validation $result, int $user_id): void
    {
        $eligibility = $this->eligibility_checker->check_user_registration_eligibility($user_id);
        if (!$eligibility['eligible']) {
            $result->invalidate('', $eligibility['message']); // General form error
        }
    }

    /**
     * Validate IČO uniqueness
     *
     * @param \WPCF7_Validation $result Validation result object
     * @param array $posted_data Form data
     * @param int $user_id Current user ID
     */
    private function validate_ico_uniqueness(\WPCF7_Validation $result, array $posted_data, int $user_id): void
    {
        $ico = sanitize_text_field($posted_data['ico'] ?? '');
        if ($this->business_manager->is_ico_already_registered($ico, $user_id)) {
            $result->invalidate('ico', 'Toto IČO je již registrováno na jiný účet.');
        }
    }
}