<?php

declare(strict_types=1);

namespace MistrFachman\Users;

use MistrFachman\Services\UserDetectionService;

/**
 * Registration Hooks Class
 *
 * Handles user registration lifecycle events including OTP registration
 * and Contact Form 7 final registration form submission.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RegistrationHooks {

    public function __construct(
        private RoleManager $role_manager,
        private UserDetectionService $user_detection_service,
        private BusinessDataValidator $business_validator,
        private BusinessDataManager $business_manager
    ) {}

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks(): void {
        add_action('user_register', [$this, 'set_initial_user_role_on_creation'], 10, 1);
        
        // Hook into Contact Form 7's validation (before mail sending)
        add_action('wpcf7_validate', [$this, 'validate_registration_form'], 10, 2);
        
        // Hook into successful submission (after mail sending)
        add_action('wpcf7_mail_sent', [$this, 'handle_final_registration_submission'], 10, 1);
    }

    /**
     * Set initial user role and status when user registers via OTP
     *
     * @param int $user_id The ID of the newly created user
     */
    public function set_initial_user_role_on_creation(int $user_id): void {
        $user = get_userdata($user_id);
        
        if (!$user) {
            mycred_debug('Failed to get user data for new registration', ['user_id' => $user_id], 'users', 'error');
            return;
        }

        mycred_debug('Processing new user registration', [
            'user_id' => $user_id,
            'user_login' => $user->user_login,
            'is_numeric' => is_numeric($user->user_login),
            'matches_phone_pattern' => preg_match('/^\d+(-\d+)?$/', $user->user_login),
            'current_roles' => $user->roles
        ], 'users', 'info');

        // Check if this is a phone number registration
        if (RegistrationConfig::isPhoneRegistration($user->user_login)) {
            // Set the pending approval role
            $user->set_role(RoleManager::PENDING_APPROVAL);
            
            // Set the initial status indicating the user needs to fill the form
            $status_updated = $this->role_manager->update_user_status($user_id, RegistrationStatus::NEEDS_FORM);
            
            // Refresh user data to verify role was set
            $updated_user = get_userdata($user_id);
            
            mycred_debug('New OTP user registered with pending role', [
                'user_id' => $user_id,
                'phone' => $user->user_login,
                'role_set' => RoleManager::PENDING_APPROVAL,
                'status_updated' => $status_updated,
                'final_roles' => $updated_user->roles,
                'meta_status' => get_user_meta($user_id, RoleManager::REG_STATUS_META_KEY, true)
            ], 'users', 'info');
        } else {
            mycred_debug('User registration skipped - not numeric login', [
                'user_id' => $user_id,
                'user_login' => $user->user_login
            ], 'users', 'info');
        }
    }

    /**
     * Validate Contact Form 7 registration form before processing
     *
     * This runs before mail sending, allowing us to add validation errors
     * without breaking the CF7 flow or LeadHub integration.
     *
     * @param \WPCF7_Validation $result Validation result object
     * @param \WPCF7_FormTag[] $tags Array of form tags
     */
    public function validate_registration_form(\WPCF7_Validation $result, array $tags): void {
        $contact_form = wpcf7_get_current_contact_form();
        
        if (!$contact_form || !RegistrationConfig::isRegistrationForm($contact_form)) {
            return;
        }

        mycred_debug('CF7 registration form validation started', [
            'submitted_form_id' => $contact_form->id(),
            'form_title' => $contact_form->title()
        ], 'users', 'info');

        // Get form data for validation
        $submission = \WPCF7_Submission::get_instance();
        $posted_data = $submission ? $submission->get_posted_data() : [];

        // Detect user using multiple methods
        $user_id = $this->user_detection_service->detectUser($posted_data);
        
        if (!$user_id) {
            $result->invalidate('email', 'Uživatel není přihlášen. Nejprve se přihlaste pomocí SMS kódu.');
            mycred_debug('Registration form validation failed - user not logged in', [
                'form_id' => $contact_form->id(),
                'detection_context' => $this->user_detection_service->getDetectionContext()
            ], 'users', 'warning');
            return;
        }

        // Check if user is eligible for registration
        $eligibility_check = $this->check_user_registration_eligibility($user_id);
        if (!$eligibility_check['eligible']) {
            $result->invalidate('email', $eligibility_check['message']);
            mycred_debug('Registration form validation failed - user not eligible', [
                'user_id' => $user_id,
                'form_id' => $contact_form->id(),
                'reason' => $eligibility_check['reason'],
                'message' => $eligibility_check['message']
            ], 'users', 'warning');
            return;
        }

        // Validate business data and IČO with ARES
        try {
            $business_data = $this->business_validator->validate_and_process_business_data($posted_data);
            
            mycred_debug('Business data validation successful in CF7 validation', [
                'user_id' => $user_id,
                'form_id' => $contact_form->id(),
                'ico' => $business_data['ico'],
                'company_name' => $business_data['company_name'],
                'ares_validated' => $business_data['validation']['ares_verified']
            ], 'users', 'info');

        } catch (\Exception $e) {
            $result->invalidate('ico', 'Chyba při validaci firemních údajů: ' . $e->getMessage());
            mycred_debug('Business data validation failed in CF7 validation', [
                'user_id' => $user_id,
                'form_id' => $contact_form->id(),
                'error' => $e->getMessage(),
                'posted_data_keys' => array_keys($posted_data)
            ], 'users', 'error');
            return;
        }
    }

    /**
     * Handle successful Contact Form 7 registration form submission
     *
     * This runs after mail sending and validation, so we can safely
     * process the business data and update user status.
     *
     * @param \WPCF7_ContactForm $contact_form The submitted contact form
     */
    public function handle_final_registration_submission(\WPCF7_ContactForm $contact_form): void {
        mycred_debug('CF7 form mail sent - processing registration', [
            'submitted_form_id' => $contact_form->id(),
            'form_title' => $contact_form->title()
        ], 'users', 'info');
        
        // Check if this is the registration form
        if (!RegistrationConfig::isRegistrationForm($contact_form)) {
            return;
        }

        // Get form data for processing
        $submission = \WPCF7_Submission::get_instance();
        $posted_data = $submission ? $submission->get_posted_data() : [];

        // Detect user using multiple methods
        $user_id = $this->user_detection_service->detectUser($posted_data);
        
        if (!$user_id) {
            mycred_debug('Cannot process registration - user not detected in mail_sent hook', [
                'form_id' => $contact_form->id()
            ], 'users', 'error');
            return;
        }

        // Double-check eligibility (should have been validated already)
        $eligibility_check = $this->check_user_registration_eligibility($user_id);
        if (!$eligibility_check['eligible']) {
            mycred_debug('Cannot process registration - user not eligible in mail_sent hook', [
                'user_id' => $user_id,
                'form_id' => $contact_form->id(),
                'reason' => $eligibility_check['reason']
            ], 'users', 'error');
            return;
        }

        // Process business data (should have been validated already)
        try {
            $business_data = $this->business_validator->validate_and_process_business_data($posted_data);
            
            // Store validated business data
            $this->business_manager->store_business_data($user_id, $business_data);
            
            // Sync business data to WordPress user profile
            $this->sync_business_data_to_user_profile($user_id, $business_data);
            
            // Update status to awaiting_review after successful processing
            $this->role_manager->update_user_status($user_id, RegistrationStatus::AWAITING_REVIEW);
            
            mycred_debug('Business registration processed successfully', [
                'user_id' => $user_id,
                'form_id' => $contact_form->id(),
                'ico' => $business_data['ico'],
                'company_name' => $business_data['company_name'],
                'ares_validated' => $business_data['validation']['ares_verified'],
                'new_status' => RegistrationStatus::AWAITING_REVIEW
            ], 'users', 'info');

            // Send notification to administrators
            do_action('mistr_fachman_user_awaiting_review', $user_id, $contact_form);

        } catch (\Exception $e) {
            mycred_debug('Business registration processing failed in mail_sent hook', [
                'user_id' => $user_id,
                'form_id' => $contact_form->id(),
                'error' => $e->getMessage()
            ], 'users', 'error');
            
            // Log error but don't break the flow - mail has already been sent
            error_log('Registration processing failed after mail sent: ' . $e->getMessage());
        }
    }

    /**
     * Promote user to full member (for admin use)
     *
     * @param int $user_id User ID to promote
     * @return bool Success status
     */
    public function promote_to_full_member(int $user_id): bool {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }

        // Verify user is currently pending
        if (!$this->role_manager->is_pending_user($user_id)) {
            mycred_debug('Attempted to promote non-pending user', [
                'user_id' => $user_id
            ], 'users', 'warning');
            return false;
        }

        // Set full member role
        $user->set_role(RoleManager::FULL_MEMBER);
        
        // Clear the registration status meta
        delete_user_meta($user_id, RoleManager::REG_STATUS_META_KEY);
        
        mycred_debug('User promoted to full member', [
            'user_id' => $user_id,
            'previous_role' => RoleManager::PENDING_APPROVAL,
            'new_role' => RoleManager::FULL_MEMBER
        ], 'users', 'info');

        // Trigger action for other systems
        do_action('mistr_fachman_user_promoted', $user_id);

        return true;
    }

    /**
     * Revoke user membership back to pending status
     *
     * @param int $user_id User ID to revoke
     * @return bool Success status
     */
    public function revoke_to_pending(int $user_id): bool {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }

        // Verify user is currently a full member
        if (!in_array(RoleManager::FULL_MEMBER, $user->roles, true)) {
            mycred_debug('Attempted to revoke non-full-member user', [
                'user_id' => $user_id,
                'current_roles' => $user->roles
            ], 'users', 'warning');
            return false;
        }

        // Set back to pending approval role
        $user->set_role(RoleManager::PENDING_APPROVAL);
        
        // Set status back to awaiting review (they already have business data)
        $this->role_manager->update_user_status($user_id, RegistrationStatus::AWAITING_REVIEW);
        
        mycred_debug('User membership revoked back to pending', [
            'user_id' => $user_id,
            'previous_role' => RoleManager::FULL_MEMBER,
            'new_role' => RoleManager::PENDING_APPROVAL,
            'new_status' => RegistrationStatus::AWAITING_REVIEW
        ], 'users', 'info');

        // Trigger action for other systems
        do_action('mistr_fachman_user_revoked', $user_id);

        return true;
    }





    /**
     * Retrieve business data for a user
     *
     * @param int $user_id User ID
     * @return array|null Business data or null if not found
     */
    public function get_business_data(int $user_id): ?array {
        return $this->business_manager->get_business_data($user_id);
    }

    /**
     * Check if user has business data stored
     *
     * @param int $user_id User ID
     * @return bool True if user has business data
     */
    public function has_business_data(int $user_id): bool {
        return $this->business_manager->has_business_data($user_id);
    }

    /**
     * Delete business data for a user
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function delete_business_data(int $user_id): bool {
        return $this->business_manager->delete_business_data($user_id);
    }

    /**
     * Check if user is eligible for registration submission
     *
     * Comprehensive security check to prevent various edge cases and attacks.
     *
     * @param int $user_id User ID to check
     * @return array{eligible: bool, reason: string, message: string} Eligibility result
     */
    private function check_user_registration_eligibility(int $user_id): array {
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

    /**
     * Sync business data to WordPress user profile fields
     *
     * @param int $user_id User ID
     * @param array $business_data Business data array
     * @return bool Success status
     */
    private function sync_business_data_to_user_profile(int $user_id, array $business_data): bool {
        $full_name_with_company = sprintf(
            '%s %s (%s)',
            $business_data['representative']['first_name'],
            $business_data['representative']['last_name'],
            $business_data['company_name']
        );

        // Update WordPress user core fields
        $user_updates = [
            'ID' => $user_id,
            'first_name' => $business_data['representative']['first_name'],
            'last_name' => $business_data['representative']['last_name'],
            'user_email' => $business_data['representative']['email'],
            'display_name' => $full_name_with_company,
            'nickname' => $full_name_with_company
        ];

        $result = wp_update_user($user_updates);
        
        if (is_wp_error($result)) {
            mycred_debug('Failed to sync business data to user profile', [
                'user_id' => $user_id,
                'error' => $result->get_error_message(),
                'updates' => $user_updates
            ], 'users', 'error');
            return false;
        }

        // Update WooCommerce billing fields
        $billing_updates = [
            'billing_first_name' => $business_data['representative']['first_name'],
            'billing_last_name' => $business_data['representative']['last_name'],
            'billing_company' => $business_data['company_name'],
            'billing_email' => $business_data['representative']['email'],
            'billing_address_1' => $business_data['address'],
            'billing_country' => 'CZ', // Czech Republic
        ];

        // Add phone if available in business data
        if (isset($business_data['representative']['phone'])) {
            $billing_updates['billing_phone'] = $business_data['representative']['phone'];
        }

        // Update billing meta fields
        foreach ($billing_updates as $meta_key => $meta_value) {
            update_user_meta($user_id, $meta_key, $meta_value);
        }

        mycred_debug('Business data synced to user profile and billing', [
            'user_id' => $user_id,
            'first_name' => $business_data['representative']['first_name'],
            'last_name' => $business_data['representative']['last_name'],
            'email' => $business_data['representative']['email'],
            'display_name' => $full_name_with_company,
            'nickname' => $full_name_with_company,
            'billing_company' => $business_data['company_name'],
            'billing_address' => $business_data['address']
        ], 'users', 'info');

        return true;
    }

    /**
     * Process realizace approval (for future implementation)
     *
     * This method will be called when admin approves a realizace post.
     * It will trigger myCred point awarding and other business logic.
     *
     * @param int $user_id User ID who submitted the realizace
     * @param int $post_id Realizace post ID
     * @param array $realizace_data Realizace data from the post
     * @param int $approved_points Points to award (calculated based on realizace type/value)
     * @return bool Success status
     */
    public function process_realizace_approval(int $user_id, int $post_id, array $realizace_data, int $approved_points = 0): bool {
        // Validate inputs
        if (!$user_id || !$post_id) {
            return false;
        }

        // Log the realizace approval
        mycred_debug('Processing realizace approval', [
            'user_id' => $user_id,
            'post_id' => $post_id,
            'approved_points' => $approved_points,
            'realizace_data_keys' => array_keys($realizace_data)
        ], 'users', 'info');

        // Trigger hook for myCred integration and other systems
        // This hook will be used by myCred to award points
        do_action('mistr_fachman_realizace_approved', $user_id, $post_id, $realizace_data, $approved_points);

        // Additional future logic:
        // - Update realizace post status
        // - Send notification to user
        // - Update user statistics
        // - Log admin approval action

        return true;
    }

    /**
     * Process realizace rejection (for future implementation)
     *
     * @param int $user_id User ID who submitted the realizace
     * @param int $post_id Realizace post ID
     * @param string $rejection_reason Reason for rejection
     * @return bool Success status
     */
    public function process_realizace_rejection(int $user_id, int $post_id, string $rejection_reason = ''): bool {
        // Validate inputs
        if (!$user_id || !$post_id) {
            return false;
        }

        // Log the realizace rejection
        mycred_debug('Processing realizace rejection', [
            'user_id' => $user_id,
            'post_id' => $post_id,
            'rejection_reason' => $rejection_reason
        ], 'users', 'info');

        // Trigger hook for notification systems
        do_action('mistr_fachman_realizace_rejected', $user_id, $post_id, $rejection_reason);

        return true;
    }

}