<?php

declare(strict_types=1);

namespace MistrFachman\Services;

use MistrFachman\Users\RoleManager;
use MistrFachman\Users\RegistrationStatus;

/**
 * User Service Class
 *
 * Central service for user status checks and user-related operations.
 * Provides a unified interface for determining user registration status
 * and access permissions.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UserService {

    public function __construct(
        private RoleManager $role_manager,
        private ?UserStatusService $user_status_service = null
    ) {}

    /**
     * Get comprehensive user registration status
     *
     * @param int $user_id User ID (0 for current user)
     * @return string Status: 'logged_out', 'full_member', 'awaiting_review', 'needs_form', 'other'
     */
    public function get_user_registration_status(int $user_id = 0): string {
        // Always delegate to UserStatusService to enforce single source of truth
        if ($this->user_status_service) {
            return $this->user_status_service->getCurrentUserStatus($user_id ?: null);
        }
        
        // Fail gracefully if the essential status service is missing
        mycred_debug('UserStatusService not available in UserService', [
            'user_id' => $user_id,
            'method' => 'get_user_registration_status'
        ], 'users', 'error');
        
        return 'other'; // Safe default status
    }

    /**
     * Check if user can make purchases
     */
    public function can_user_purchase(int $user_id = 0): bool {
        $status = $this->get_user_registration_status($user_id);
        
        // Only full members can make purchases
        return $status === 'full_member';
    }

    /**
     * Check if user can view products
     */
    public function can_user_view_products(int $user_id = 0): bool {
        $status = $this->get_user_registration_status($user_id);
        
        // All logged in users can view products
        return in_array($status, ['full_member', 'awaiting_review', 'needs_form'], true);
    }

    /**
     * Get user status display name
     */
    public function get_status_display_name(string $status): string {
        return match ($status) {
            'logged_out' => 'Nepřihlášen',
            'full_member' => 'Člen',
            'awaiting_review' => RegistrationStatus::getDisplayName(RegistrationStatus::AWAITING_REVIEW),
            'needs_form' => RegistrationStatus::getDisplayName(RegistrationStatus::NEEDS_FORM),
            'other' => 'Ostatní',
            default => 'Neznámý'
        };
    }

    /**
     * Get CSS class for user status
     */
    public function get_status_css_class(string $status): string {
        return 'user-status-' . sanitize_html_class($status);
    }

    /**
     * USER ACTION METHODS
     * These methods perform actions on users (promote, revoke, etc.)
     */

    /**
     * Promote user to full member (admin action)
     * 
     * @param int $user_id User ID to promote
     * @return bool Success status
     */
    public function promoteToFullMember(int $user_id): bool {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Verify user is currently pending using status service
        if ($this->user_status_service && !$this->user_status_service->isUserPending($user_id)) {
            mycred_debug('Attempted to promote non-pending user', [
                'user_id' => $user_id,
                'current_status' => $this->user_status_service->getCurrentUserStatus($user_id)
            ], 'user_service', 'warning');
            return false;
        }

        // Change role from pending_approval to full_member
        $user->set_role(\MistrFachman\Users\RoleManager::FULL_MEMBER);
        
        // Clear registration status meta (no longer needed)
        delete_user_meta($user_id, \MistrFachman\Users\RoleManager::REG_STATUS_META_KEY);
        
        mycred_debug('User promoted to full member', [
            'user_id' => $user_id,
            'new_status' => $this->get_user_registration_status($user_id)
        ], 'user_service', 'info');

        return true;
    }

    /**
     * Revoke user to pending approval (admin action)
     * 
     * @param int $user_id User ID to revoke
     * @return bool Success status
     */
    public function revokeToPending(int $user_id): bool {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Change role from full_member to pending_approval
        $user->set_role(\MistrFachman\Users\RoleManager::PENDING_APPROVAL);
        
        // Set status to awaiting review (they can be re-approved)
        $this->role_manager->update_user_status($user_id, RegistrationStatus::AWAITING_REVIEW);
        
        mycred_debug('User revoked to pending approval', [
            'user_id' => $user_id,
            'new_status' => $this->get_user_registration_status($user_id)
        ], 'user_service', 'info');

        return true;
    }

    /**
     * Update user registration status (form submission)
     * 
     * @param int $user_id User ID
     * @param string $status New status
     * @return bool Success status
     */
    public function updateRegistrationStatus(int $user_id, string $status): bool {
        if (!RegistrationStatus::isValidStatus($status)) {
            return false;
        }

        return $this->role_manager->update_user_status($user_id, $status);
    }
}