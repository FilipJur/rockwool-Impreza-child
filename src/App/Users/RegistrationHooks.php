<?php

declare(strict_types=1);

namespace MistrFachman\Users;

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
        private RoleManager $role_manager
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

        // Check if this is a phone number registration (numeric login or phone with suffix)
        $is_phone_registration = is_numeric($user->user_login) || 
                                preg_match('/^\d+(-\d+)?$/', $user->user_login);
        
        if ($is_phone_registration) {
            // Set the pending approval role
            $user->set_role(RoleManager::PENDING_APPROVAL);
            
            // Set the initial status indicating the user needs to fill the form
            $status_updated = $this->role_manager->update_user_status($user_id, 'needs_form');
            
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
     * This method will be called when any CF7 form is submitted.
     * TODO: Configure the correct form ID in the Users Manager
     *
     * @param \WPCF7_ContactForm $contact_form The submitted contact form
     */
    public function handle_final_registration_submission(\WPCF7_ContactForm $contact_form): void {
        // Get the configured form ID from the manager or use a default
        $registration_form_id = apply_filters('mistr_fachman_registration_form_id', 0);
        
        mycred_debug('CF7 form submission detected', [
            'submitted_form_id' => $contact_form->id(),
            'configured_form_id' => $registration_form_id,
            'form_title' => $contact_form->title(),
            'user_id' => get_current_user_id()
        ], 'users', 'info');
        
        if ($registration_form_id === 0) {
            mycred_debug('Registration form ID not configured', [
                'submitted_form_id' => $contact_form->id()
            ], 'users', 'warning');
            return;
        }

        // Ensure this is the correct form
        if ($contact_form->id() !== $registration_form_id) {
            mycred_debug('Form ID mismatch - ignoring submission', [
                'submitted_form_id' => $contact_form->id(),
                'expected_form_id' => $registration_form_id
            ], 'users', 'info');
            return;
        }

        $user_id = get_current_user_id();
        
        if (!$user_id) {
            mycred_debug('Final registration form submitted by non-logged user', [
                'form_id' => $contact_form->id()
            ], 'users', 'warning');
            return;
        }

        // Verify user has pending approval role
        if (!$this->role_manager->is_pending_user($user_id)) {
            mycred_debug('Final registration form submitted by non-pending user', [
                'user_id' => $user_id,
                'form_id' => $contact_form->id()
            ], 'users', 'warning');
            return;
        }

        // Update the user's status to show they've submitted the form
        $this->role_manager->update_user_status($user_id, 'awaiting_review');
        
        mycred_debug('User submitted final registration form', [
            'user_id' => $user_id,
            'form_id' => $contact_form->id(),
            'new_status' => 'awaiting_review'
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