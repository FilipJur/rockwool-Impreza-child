<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * User Profile Sync - Handles syncing business data to WordPress user profiles
 *
 * Manages the synchronization of business registration data to WordPress user
 * core fields and WooCommerce billing fields.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UserProfileSync {

    /**
     * Sync business data to WordPress user profile fields
     *
     * @param int $user_id User ID
     * @param array $business_data Business data array
     * @return bool Success status
     */
    public function sync_business_data_to_user_profile(int $user_id, array $business_data): bool
    {
        mycred_debug('SYNC FUNCTION: Starting user profile sync', [
            'user_id' => $user_id,
            'business_data_keys' => array_keys($business_data),
            'representative_data' => $business_data['representative'] ?? 'MISSING'
        ], 'users', 'info');

        $full_name_with_company = sprintf(
            '%s %s (%s)',
            $business_data['representative']['first_name'],
            $business_data['representative']['last_name'],
            $business_data['company_name']
        );

        mycred_debug('SYNC FUNCTION: Full name with company created', [
            'user_id' => $user_id,
            'full_name_with_company' => $full_name_with_company,
            'first_name' => $business_data['representative']['first_name'],
            'last_name' => $business_data['representative']['last_name'],
            'company_name' => $business_data['company_name']
        ], 'users', 'info');

        // Update WordPress user core fields
        $user_updates = [
            'ID' => $user_id,
            'first_name' => $business_data['representative']['first_name'],
            'last_name' => $business_data['representative']['last_name'],
            'user_email' => $business_data['representative']['email'],
            'display_name' => $full_name_with_company,
            'nickname' => $full_name_with_company
        ];

        mycred_debug('SYNC FUNCTION: About to call wp_update_user', [
            'user_id' => $user_id,
            'user_updates' => $user_updates
        ], 'users', 'info');

        $result = wp_update_user($user_updates);

        mycred_debug('SYNC FUNCTION: wp_update_user result', [
            'user_id' => $user_id,
            'result' => $result,
            'is_wp_error' => is_wp_error($result),
            'error_message' => is_wp_error($result) ? $result->get_error_message() : 'N/A'
        ], 'users', 'info');

        if (is_wp_error($result)) {
            // Check if this is an email conflict that should have been caught by validation
            if (strpos($result->get_error_message(), 'e-mailov') !== false) {
                mycred_debug('CRITICAL: Email conflict during profile sync - validation should have caught this', [
                    'user_id' => $user_id,
                    'attempted_email' => $business_data['representative']['email'],
                    'error' => $result->get_error_message(),
                    'updates' => $user_updates
                ], 'users', 'error');
            } else {
                mycred_debug('Failed to sync business data to user profile', [
                    'user_id' => $user_id,
                    'error' => $result->get_error_message(),
                    'updates' => $user_updates
                ], 'users', 'error');
            }
            return false;
        }

        // Update WooCommerce billing fields
        $this->sync_billing_fields($user_id, $business_data);

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
     * Sync business data to WooCommerce billing fields
     *
     * @param int $user_id User ID
     * @param array $business_data Business data array
     */
    private function sync_billing_fields(int $user_id, array $business_data): void
    {
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

        mycred_debug('SYNC FUNCTION: About to update billing meta fields', [
            'user_id' => $user_id,
            'billing_updates' => $billing_updates
        ], 'users', 'info');

        // Update billing meta fields
        foreach ($billing_updates as $meta_key => $meta_value) {
            $meta_result = update_user_meta($user_id, $meta_key, $meta_value);
            
            mycred_debug('SYNC FUNCTION: Updated meta field', [
                'user_id' => $user_id,
                'meta_key' => $meta_key,
                'meta_value' => $meta_value,
                'update_result' => $meta_result
            ], 'users', 'info');
        }
    }
}