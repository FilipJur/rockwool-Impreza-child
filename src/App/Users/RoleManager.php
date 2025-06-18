<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Role Manager Class
 *
 * Manages custom user roles and provides role-related constants
 * for the Mistr Fachman user registration system.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RoleManager {

    public const PENDING_APPROVAL = 'pending_approval';
    public const FULL_MEMBER = 'full_member';
    public const REG_STATUS_META_KEY = '_mistr_fachman_registration_status';

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks(): void {
        add_action('init', [$this, 'register_custom_roles']);
    }

    /**
     * Register custom user roles
     */
    public function register_custom_roles(): void {
        // Add pending approval role
        add_role(
            self::PENDING_APPROVAL,
            'Čeká na schválení',
            [
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
            ]
        );

        // Add full member role
        add_role(
            self::FULL_MEMBER,
            'Člen',
            [
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
            ]
        );

        mycred_debug('Custom user roles registered', [
            'pending_role' => self::PENDING_APPROVAL,
            'member_role' => self::FULL_MEMBER
        ], 'users', 'info');
    }

    /**
     * Get user registration status from meta
     */
    public function get_user_status(int $user_id): string {
        return get_user_meta($user_id, self::REG_STATUS_META_KEY, true) ?: '';
    }

    /**
     * Update user registration status
     */
    public function update_user_status(int $user_id, string $status): bool {
        $result = update_user_meta($user_id, self::REG_STATUS_META_KEY, $status);
        return $result !== false;
    }

    /**
     * Check if user has pending approval role
     */
    public function is_pending_user(int $user_id = 0): bool {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        return $user && in_array(self::PENDING_APPROVAL, $user->roles, true);
    }

    /**
     * Check if user is full member
     */
    public function is_full_member(int $user_id = 0): bool {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        return $user && in_array(self::FULL_MEMBER, $user->roles, true);
    }
}