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
        private UserDetectionService $user_detection_service
    ) {}

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks(): void {
        add_action('user_register', [$this, 'set_initial_user_role_on_creation'], 10, 1);
        
        // Hook into Contact Form 7's submission - form ID will need to be configured
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
     * Handle Contact Form 7 final registration form submission
     *
     * @param \WPCF7_ContactForm $contact_form The submitted contact form
     */
    public function handle_final_registration_submission(\WPCF7_ContactForm $contact_form): void {
        mycred_debug('CF7 form submission detected', [
            'submitted_form_id' => $contact_form->id(),
            'form_title' => $contact_form->title(),
            'detection_context' => $this->user_detection_service->getDetectionContext()
        ], 'users', 'info');
        
        // Check if this is the registration form
        if (!RegistrationConfig::isRegistrationForm($contact_form)) {
            mycred_debug('Form ID mismatch - ignoring submission', [
                'submitted_form_id' => $contact_form->id(),
                'expected_form_id' => RegistrationConfig::getFormId()
            ], 'users', 'info');
            return;
        }

        // Get form data for user detection fallback
        $submission = \WPCF7_Submission::get_instance();
        $posted_data = $submission ? $submission->get_posted_data() : [];

        // Detect user using multiple methods
        $user_id = $this->user_detection_service->detectUser($posted_data);
        
        if (!$user_id) {
            mycred_debug('Final registration form submitted by non-logged user - skipping processing', [
                'form_id' => $contact_form->id(),
                'form_title' => $contact_form->title(),
                'note' => 'User must be logged in to submit final registration form',
                'detection_context' => $this->user_detection_service->getDetectionContext()
            ], 'users', 'warning');
            return;
        }

        // Simple check: only process if user has pending approval role
        if (!$this->role_manager->is_pending_user($user_id)) {
            mycred_debug('Final registration form submitted by non-pending user - skipping', [
                'user_id' => $user_id,
                'form_id' => $contact_form->id(),
                'note' => 'Only pending users can submit final registration form'
            ], 'users', 'info');
            return;
        }

        // Automatically update status to awaiting_review on form submission
        // No validation of current meta status - just set it directly
        $this->role_manager->update_user_status($user_id, RegistrationStatus::AWAITING_REVIEW);
        
        mycred_debug('User submitted final registration form - status updated automatically', [
            'user_id' => $user_id,
            'form_id' => $contact_form->id(),
            'new_status' => RegistrationStatus::AWAITING_REVIEW,
            'note' => 'Status updated automatically without validation'
        ], 'users', 'info');

        // Optional: Send notification to administrators
        do_action('mistr_fachman_user_awaiting_review', $user_id, $contact_form);
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

}