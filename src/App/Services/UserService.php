<?php

declare(strict_types=1);

namespace MistrFachman\Services;

use MistrFachman\Users\RoleManager;

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
        private RoleManager $role_manager
    ) {}

    /**
     * Get comprehensive user registration status
     *
     * @param int $user_id User ID (0 for current user)
     * @return string Status: 'logged_out', 'full_member', 'awaiting_review', 'needs_form', 'other'
     */
    public function get_user_registration_status(int $user_id = 0): string {
        try {
            if ($user_id === 0) {
                $user_id = get_current_user_id();
            }

            if (!$user_id) {
                return 'logged_out';
            }

            $user = get_userdata($user_id);
            if (!$user) {
                return 'logged_out';
            }

            // Debug logging to understand why status is 'other'
            $is_full_member = $this->role_manager->is_full_member($user_id);
            $is_pending = $this->role_manager->is_pending_user($user_id);
            $meta_status = $this->role_manager->get_user_status($user_id);

            mycred_debug('UserService status check', [
                'user_id' => $user_id,
                'user_roles' => $user->roles,
                'is_full_member' => $is_full_member,
                'is_pending' => $is_pending,
                'meta_status' => $meta_status,
                'pending_role_constant' => RoleManager::PENDING_APPROVAL,
                'full_member_constant' => RoleManager::FULL_MEMBER
            ], 'users', 'info');

            // Check if user is full member
            if ($is_full_member) {
                return 'full_member';
            }

            // Check if user is pending approval
            if ($is_pending) {
                return $meta_status === 'awaiting_review' ? 'awaiting_review' : 'needs_form';
            }

            return 'other';
        } catch (\Exception $e) {
            mycred_debug('UserService error', ['error' => $e->getMessage()], 'users', 'error');
            // Fail safely during API requests or when services aren't available
            return 'logged_out';
        }
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
            'awaiting_review' => 'Čeká na schválení',
            'needs_form' => 'Vyplnit formulář',
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
}