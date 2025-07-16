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

class RegistrationHooks
{

	public function __construct(
		private RoleManager $role_manager,
		private UserDetectionService $user_detection_service,
		private BusinessDataValidator $business_validator,
		private BusinessDataManager $business_manager,
		private UserProfileSync $profile_sync,
		private RegistrationValidator $registration_validator,
		private RegistrationEligibility $eligibility_checker,
		private BusinessDataProcessor $data_processor,
		private ?\MistrFachman\Services\UserService $user_service = null
	) {
	}

	/**
	 * Initialize WordPress hooks
	 */
	public function init_hooks(): void
	{
		add_action('user_register', [$this, 'set_initial_user_role_on_creation'], 10, 1);

		// Hook into successful submission (after mail sending)
		// All validation will be done here to avoid interfering with CF7's internal validation
		add_action('wpcf7_mail_sent', [$this, 'handle_final_registration_submission'], 10, 1);

		// Add validation hook as security gate
		add_filter('wpcf7_validate', [$this->registration_validator, 'validate_registration_form_submission'], 20, 2);

		// Add hook for ARES form scripts - runs on every frontend page
		add_action('wp_enqueue_scripts', [$this, 'enqueue_ares_form_scripts']);

		// Award registration bonus when user is promoted to full member
		add_action('mistr_fachman_user_promoted', [$this, 'award_registration_bonus'], 10, 1);
	}

	/**
	 * Set initial user role and status when user registers via OTP
	 *
	 * @param int $user_id The ID of the newly created user
	 */
	public function set_initial_user_role_on_creation(int $user_id): void
	{
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
	 * Handle successful Contact Form 7 registration form submission
	 *
	 * This runs after mail sending and CF7's internal validation is complete.
	 * We perform our own validation and processing here to avoid interfering
	 * with CF7's validation flow.
	 *
	 * @param \WPCF7_ContactForm $contact_form The submitted contact form
	 */
	public function handle_final_registration_submission(\WPCF7_ContactForm $contact_form): void
	{
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
				'form_id' => $contact_form->id(),
				'detection_context' => $this->user_detection_service->getDetectionContext()
			], 'users', 'error');
			return;
		}

		// Check eligibility - this is our main validation now
		$eligibility_check = $this->eligibility_checker->check_user_registration_eligibility($user_id);
		if (!$eligibility_check['eligible']) {
			mycred_debug('Cannot process registration - user not eligible', [
				'user_id' => $user_id,
				'form_id' => $contact_form->id(),
				'reason' => $eligibility_check['reason'],
				'message' => $eligibility_check['message']
			], 'users', 'error');
			return;
		}

		// Because wpcf7_validate passed, we know the submission is valid.
		try {
			$submission = \WPCF7_Submission::get_instance();
			$posted_data = $submission ? $submission->get_posted_data() : [];

			mycred_debug('REGISTRATION FLOW: Starting data processing', [
				'user_id' => $user_id,
				'form_id' => $contact_form->id(),
				'posted_data_keys' => array_keys($posted_data),
				'posted_data_sample' => [
					'ico' => $posted_data['ico'] ?? 'MISSING',
					'company-name' => $posted_data['company-name'] ?? 'MISSING',
					'first-name' => $posted_data['first-name'] ?? 'MISSING',
					'last-name' => $posted_data['last-name'] ?? 'MISSING',
					'contact-email' => $posted_data['contact-email'] ?? 'MISSING'
				]
			], 'users', 'info');

			// 1. Shape the data. This will correctly handle the optional `position` field.
			$business_data = $this->data_processor->shape_business_data($posted_data, $user_id);

			mycred_debug('REGISTRATION FLOW: Business data shaped', [
				'user_id' => $user_id,
				'shaped_data_structure' => [
					'ico' => $business_data['ico'] ?? 'MISSING',
					'company_name' => $business_data['company_name'] ?? 'MISSING',
					'address' => $business_data['address'] ?? 'MISSING',
					'representative' => [
						'first_name' => $business_data['representative']['first_name'] ?? 'MISSING',
						'last_name' => $business_data['representative']['last_name'] ?? 'MISSING',
						'email' => $business_data['representative']['email'] ?? 'MISSING',
						'position' => $business_data['representative']['position'] ?? 'MISSING'
					],
					'ares_verified' => $business_data['validation']['ares_verified'] ?? false
				]
			], 'users', 'info');

			mycred_debug('Business data validation successful', [
				'user_id' => $user_id,
				'form_id' => $contact_form->id(),
				'ico' => $business_data['ico'],
				'company_name' => $business_data['company_name'],
				'ares_validated' => $business_data['validation']['ares_verified']
			], 'users', 'info');

			// 2. Store the business data.
			$this->business_manager->store_business_data($user_id, $business_data);

			mycred_debug('REGISTRATION FLOW: Business data stored', [
				'user_id' => $user_id,
				'store_success' => true
			], 'users', 'info');

			// 3. THIS WILL NOW SUCCEED: Sync data to user profile.
			$sync_result = $this->profile_sync->sync_business_data_to_user_profile($user_id, $business_data);

			mycred_debug('REGISTRATION FLOW: Profile sync attempted', [
				'user_id' => $user_id,
				'sync_result' => $sync_result,
				'data_being_synced' => [
					'first_name' => $business_data['representative']['first_name'],
					'last_name' => $business_data['representative']['last_name'],
					'email' => $business_data['representative']['email'],
					'company_name' => $business_data['company_name'],
					'address' => $business_data['address']
				]
			], 'users', 'info');

			// 4. Update the user's status.
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
			// This is now a true failsafe for processing errors.
			mycred_debug('CRITICAL ERROR during final registration processing', [
				'user_id' => $user_id,
				'form_id' => $contact_form->id(),
				'error' => $e->getMessage(),
				'posted_data_keys' => array_keys($posted_data)
			], 'users', 'error');

			error_log('CRITICAL ERROR during final registration processing for user ' . $user_id . ': ' . $e->getMessage());
		}
	}

	/**
	 * Promote user to full member (for admin use)
	 *
	 * @param int $user_id User ID to promote
	 * @return bool Success status
	 */
	public function promote_to_full_member(int $user_id): bool
	{
		// Delegate to UserService if available, otherwise fall back to original logic
		if ($this->user_service) {
			$success = $this->user_service->promoteToFullMember($user_id);
			if ($success) {
				// Trigger action for other systems
				do_action('mistr_fachman_user_promoted', $user_id);
			}
			return $success;
		}

		// FALLBACK: Original logic for backward compatibility
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
	public function revoke_to_pending(int $user_id): bool
	{
		// Delegate to UserService if available, otherwise fall back to original logic
		if ($this->user_service) {
			return $this->user_service->revokeToPending($user_id);
		}

		// FALLBACK: Original logic for backward compatibility
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
	public function get_business_data(int $user_id): ?array
	{
		return $this->business_manager->get_business_data($user_id);
	}

	/**
	 * Check if user has business data stored
	 *
	 * @param int $user_id User ID
	 * @return bool True if user has business data
	 */
	public function has_business_data(int $user_id): bool
	{
		return $this->business_manager->has_business_data($user_id);
	}

	/**
	 * Delete business data for a user
	 *
	 * @param int $user_id User ID
	 * @return bool Success status
	 */
	public function delete_business_data(int $user_id): bool
	{
		return $this->business_manager->delete_business_data($user_id);
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
	public function process_realizace_approval(int $user_id, int $post_id, array $realizace_data, int $approved_points = 0): bool
	{
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
	public function process_realizace_rejection(int $user_id, int $post_id, string $rejection_reason = ''): bool
	{
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


	/**
	 * Enqueues scripts needed for ARES forms across the entire site.
	 * The JavaScript itself will check if the form exists before running.
	 */
	public function enqueue_ares_form_scripts(): void
	{
		// Do not run in the admin backend.
		if (is_admin()) {
			return;
		}

		// 1. Enqueue the main script on all frontend pages.
		// This ensures it's available wherever a CF7 form might be placed.
		wp_enqueue_script('theme-main-js');

		// 2. Always localize the data. The JS object is small and harmless.
		if (!wp_script_is('mistrFachmanAjax', 'data')) {
			wp_localize_script(
				'theme-main-js',
				'mistrFachmanAjax',
				[
					'ajax_url' => admin_url('admin-ajax.php'),
					'ico_validation_nonce' => wp_create_nonce('mistr_fachman_ico_validation_nonce')
				]
			);
		}
	}

	/**
	 * Award registration bonus points when user is promoted to full member
	 *
	 * @param int $user_id User ID who was promoted
	 */
	public function award_registration_bonus(int $user_id): void
	{
		// Import the required DualPointsManager class
		$dual_manager = new \MistrFachman\Services\DualPointsManager();
		
		// Get the registration bonus amount from configuration
		$bonus_points = \MistrFachman\Services\DomainConfigurationService::getUserWorkflowReward('registration_completed');
		
		mycred_debug('Awarding registration bonus', [
			'user_id' => $user_id,
			'bonus_points' => $bonus_points,
			'source' => 'RegistrationHooks::award_registration_bonus'
		], 'users', 'info');
		
		// Award the registration bonus
		$result = $dual_manager->awardDualPoints(
			$user_id,
			$bonus_points,
			'registration_bonus',
			'Bonus za dokončení registrace: vítejte v systému!',
			0 // No post_id for registration events
		);
		
		if ($result) {
			mycred_debug('Registration bonus awarded successfully', [
				'user_id' => $user_id,
				'points_awarded' => $bonus_points,
				'description' => 'Registration completion bonus'
			], 'users', 'info');
		} else {
			mycred_debug('Failed to award registration bonus', [
				'user_id' => $user_id,
				'points_attempted' => $bonus_points,
				'error' => 'DualPointsManager::awardDualPoints() returned false'
			], 'users', 'error');
		}
	}

}
