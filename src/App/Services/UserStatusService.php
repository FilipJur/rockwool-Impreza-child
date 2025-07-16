<?php

declare(strict_types=1);

namespace MistrFachman\Services;

use MistrFachman\Users\RoleManager;
use MistrFachman\Users\RegistrationStatus;

/**
 * User Status Service - Single Source of Truth
 *
 * Unifies all user status checking logic across the application.
 * Provides consistent status constants and methods for all components.
 * Maintains backward compatibility with existing status strings.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UserStatusService {

    // Status constants - single source of truth
    public const STATUS_GUEST = 'logged_out';
    public const STATUS_NEEDS_FORM = 'needs_form';
    public const STATUS_AWAITING_APPROVAL = 'awaiting_review';
    public const STATUS_FULL_MEMBER = 'full_member';
    public const STATUS_OTHER = 'other';

    // Legacy status constants for backward compatibility
    public const LEGACY_STATUS_AWAITING_REVIEW = 'awaiting_review';
    public const LEGACY_STATUS_APPROVED = 'approved';

    public function __construct(
        private RoleManager $role_manager
    ) {}

    /**
     * Get current user status - primary method for all components
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return string Status constant
     */
    public function getCurrentUserStatus(?int $user_id = null): string {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Guest user (not logged in)
        if ($user_id === 0) {
            return self::STATUS_GUEST;
        }

        // Get user data
        $user = get_userdata($user_id);
        if (!$user) {
            return self::STATUS_GUEST;
        }

        // Check if user is full member (highest priority)
        if ($this->role_manager->is_full_member($user_id)) {
            return self::STATUS_FULL_MEMBER;
        }

        // Check if user is pending approval
        if ($this->role_manager->is_pending_user($user_id)) {
            // Get granular status from meta field
            $meta_status = $this->role_manager->get_user_status($user_id);
            
            return match ($meta_status) {
                RegistrationStatus::NEEDS_FORM => self::STATUS_NEEDS_FORM,
                RegistrationStatus::AWAITING_REVIEW => self::STATUS_AWAITING_APPROVAL,
                RegistrationStatus::APPROVED => self::STATUS_FULL_MEMBER, // Legacy status
                default => self::STATUS_NEEDS_FORM // Default for pending users
            };
        }

        // Check for other WordPress roles (admin, editor, etc.)
        if ($this->isOtherValidRole($user)) {
            return self::STATUS_OTHER;
        }

        // Fallback for unknown states
        return self::STATUS_OTHER;
    }

    /**
     * Check if user is approved (full member or other valid role)
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return bool True if user is approved
     */
    public function isUserApproved(?int $user_id = null): bool {
        $status = $this->getCurrentUserStatus($user_id);
        
        return in_array($status, [
            self::STATUS_FULL_MEMBER,
            self::STATUS_OTHER
        ], true);
    }

    /**
     * Check if user can access a specific feature
     * 
     * @param string $feature Feature identifier
     * @param int|null $user_id User ID (null for current user)
     * @return bool True if user can access feature
     */
    public function canUserAccessFeature(string $feature, ?int $user_id = null): bool {
        $status = $this->getCurrentUserStatus($user_id);
        
        return match ($feature) {
            'ecommerce' => $this->isUserApproved($user_id),
            'dashboard' => $status !== self::STATUS_GUEST,
            'registration_form' => $status === self::STATUS_NEEDS_FORM,
            'admin_approval' => $status === self::STATUS_AWAITING_APPROVAL,
            'realizations' => $this->isUserApproved($user_id),
            'invoices' => $this->isUserApproved($user_id),
            default => false
        };
    }

    /**
     * Check if user is in pending state (needs form or awaiting approval)
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return bool True if user is pending
     */
    public function isUserPending(?int $user_id = null): bool {
        $status = $this->getCurrentUserStatus($user_id);
        
        return in_array($status, [
            self::STATUS_NEEDS_FORM,
            self::STATUS_AWAITING_APPROVAL
        ], true);
    }

    /**
     * Check if user is guest (not logged in)
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return bool True if user is guest
     */
    public function isUserGuest(?int $user_id = null): bool {
        return $this->getCurrentUserStatus($user_id) === self::STATUS_GUEST;
    }

    /**
     * Get user-friendly status display name
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return string Localized status name
     */
    public function getStatusDisplayName(?int $user_id = null): string {
        $status = $this->getCurrentUserStatus($user_id);
        
        return match ($status) {
            self::STATUS_GUEST => 'Nepřihlášen',
            self::STATUS_NEEDS_FORM => 'Vyplnit formulář',
            self::STATUS_AWAITING_APPROVAL => 'Čeká na schválení',
            self::STATUS_FULL_MEMBER => 'Člen',
            self::STATUS_OTHER => 'Jiný uživatel',
            default => 'Neznámý stav'
        };
    }

    /**
     * Get status CSS class for styling
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return string CSS class name
     */
    public function getStatusCssClass(?int $user_id = null): string {
        $status = $this->getCurrentUserStatus($user_id);
        
        return match ($status) {
            self::STATUS_GUEST => 'status-guest',
            self::STATUS_NEEDS_FORM => 'status-needs-form',
            self::STATUS_AWAITING_APPROVAL => 'status-awaiting-approval',
            self::STATUS_FULL_MEMBER => 'status-full-member',
            self::STATUS_OTHER => 'status-other',
            default => 'status-unknown'
        };
    }

    /**
     * Get all valid status constants
     * 
     * @return array<string> Array of status constants
     */
    public static function getAllStatuses(): array {
        return [
            self::STATUS_GUEST,
            self::STATUS_NEEDS_FORM,
            self::STATUS_AWAITING_APPROVAL,
            self::STATUS_FULL_MEMBER,
            self::STATUS_OTHER
        ];
    }

    /**
     * Check if status is valid
     * 
     * @param string $status Status to validate
     * @return bool True if status is valid
     */
    public static function isValidStatus(string $status): bool {
        return in_array($status, self::getAllStatuses(), true);
    }


    /**
     * BACKWARD COMPATIBILITY METHODS
     * These methods maintain existing API for components
     */

    /**
     * Get user registration status (legacy method for backward compatibility)
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return string Status string matching existing UserService API
     */
    public function get_user_registration_status(?int $user_id = null): string {
        return $this->getCurrentUserStatus($user_id);
    }

    /**
     * Check if user has other valid WordPress role
     * 
     * @param \WP_User $user WordPress user object
     * @return bool True if user has other valid role
     */
    private function isOtherValidRole(\WP_User $user): bool {
        $valid_roles = [
            'administrator',
            'editor',
            'author',
            'contributor',
            'subscriber',
            'customer' // WooCommerce role
        ];

        return !empty(array_intersect($user->roles, $valid_roles));
    }
}