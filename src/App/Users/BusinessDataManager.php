<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Business Data Manager - Business Data Storage and Retrieval
 *
 * Handles all business data storage, retrieval, and management operations.
 * Provides clean interface for business data persistence in WordPress user meta.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BusinessDataManager {

    /**
     * Data type constants
     */
    public const DATA_TYPE_BUSINESS = 'business';
    public const DATA_TYPE_REALIZACE = 'realizace';

    /**
     * Business data meta key
     */
    private const BUSINESS_DATA_META_KEY = '_mistr_fachman_business_data';

    /**
     * Realizace data meta key (for future implementation)
     */
    private const REALIZACE_DATA_META_KEY = '_mistr_fachman_realizace_data';

    /**
     * Store business data in user meta
     *
     * @param int $user_id User ID
     * @param array $business_data Business data to store
     * @return bool Success status
     */
    public function store_business_data(int $user_id, array $business_data): bool {
        return update_user_meta($user_id, self::BUSINESS_DATA_META_KEY, $business_data) !== false;
    }

    /**
     * Retrieve business data for a user
     *
     * @param int $user_id User ID
     * @return array|null Business data or null if not found
     */
    public function get_business_data(int $user_id): ?array {
        $data = get_user_meta($user_id, self::BUSINESS_DATA_META_KEY, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Check if user has business data stored
     *
     * @param int $user_id User ID
     * @return bool True if user has business data
     */
    public function has_business_data(int $user_id): bool {
        return $this->get_business_data($user_id) !== null;
    }

    /**
     * Delete business data for a user
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function delete_business_data(int $user_id): bool {
        return delete_user_meta($user_id, self::BUSINESS_DATA_META_KEY);
    }

    /**
     * Update specific business data field
     *
     * @param int $user_id User ID
     * @param string $field_path Dot notation field path (e.g., 'representative.email')
     * @param mixed $value New value
     * @return bool Success status
     */
    public function update_business_data_field(int $user_id, string $field_path, $value): bool {
        $business_data = $this->get_business_data($user_id);
        
        if (!$business_data) {
            return false;
        }

        // Parse dot notation path
        $keys = explode('.', $field_path);
        $current = &$business_data;
        
        // Navigate to the parent of the target field
        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (!isset($current[$keys[$i]]) || !is_array($current[$keys[$i]])) {
                return false;
            }
            $current = &$current[$keys[$i]];
        }
        
        // Set the value
        $current[end($keys)] = $value;
        
        return $this->store_business_data($user_id, $business_data);
    }

    /**
     * Get business data field by path
     *
     * @param int $user_id User ID
     * @param string $field_path Dot notation field path
     * @return mixed Field value or null if not found
     */
    public function get_business_data_field(int $user_id, string $field_path) {
        $business_data = $this->get_business_data($user_id);
        
        if (!$business_data) {
            return null;
        }

        $keys = explode('.', $field_path);
        $current = $business_data;
        
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        
        return $current;
    }

    /**
     * Get all users with business data
     *
     * @return array Array of user IDs
     */
    public function get_users_with_business_data(): array {
        global $wpdb;
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
            self::BUSINESS_DATA_META_KEY
        ));
        
        return array_map('intval', $user_ids);
    }

    /**
     * Search users by business criteria
     *
     * @param array $criteria Search criteria
     * @return array Array of user IDs matching criteria
     */
    public function search_users_by_business_criteria(array $criteria): array {
        $users_with_data = $this->get_users_with_business_data();
        $matching_users = [];
        
        foreach ($users_with_data as $user_id) {
            $business_data = $this->get_business_data($user_id);
            
            if (!$business_data) {
                continue;
            }
            
            $matches = true;
            foreach ($criteria as $field => $value) {
                $data_value = $this->get_business_data_field($user_id, $field);
                
                if ($data_value !== $value) {
                    $matches = false;
                    break;
                }
            }
            
            if ($matches) {
                $matching_users[] = $user_id;
            }
        }
        
        return $matching_users;
    }

    /**
     * Export business data for a user
     *
     * @param int $user_id User ID
     * @return array|null Exported data structure or null if no data
     */
    public function export_business_data(int $user_id): ?array {
        $business_data = $this->get_business_data($user_id);
        
        if (!$business_data) {
            return null;
        }
        
        $user = get_userdata($user_id);
        
        return [
            'user_info' => [
                'id' => $user_id,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'roles' => $user->roles
            ],
            'business_data' => $business_data,
            'exported_at' => current_time('mysql')
        ];
    }

    /**
     * Sync WordPress user profile changes back to business data
     *
     * This method ensures bidirectional sync when admin edits user profile
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function sync_user_profile_to_business_data(int $user_id): bool {
        $business_data = $this->get_business_data($user_id);
        
        if (!$business_data) {
            return false; // No business data to sync
        }
        
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        // Update representative information from user profile
        $business_data['representative']['first_name'] = get_user_meta($user_id, 'first_name', true) ?: $business_data['representative']['first_name'];
        $business_data['representative']['last_name'] = get_user_meta($user_id, 'last_name', true) ?: $business_data['representative']['last_name'];
        $business_data['representative']['email'] = $user->user_email;
        
        // Add sync timestamp
        $business_data['last_profile_sync'] = current_time('mysql');
        
        return $this->store_business_data($user_id, $business_data);
    }

    /**
     * Check if business data is out of sync with user profile
     *
     * @param int $user_id User ID
     * @return bool True if data is out of sync
     */
    public function is_business_data_out_of_sync(int $user_id): bool {
        $business_data = $this->get_business_data($user_id);
        
        if (!$business_data) {
            return false;
        }
        
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        $email = $user->user_email;
        
        // Check if any representative data differs
        return $business_data['representative']['first_name'] !== $first_name ||
               $business_data['representative']['last_name'] !== $last_name ||
               $business_data['representative']['email'] !== $email;
    }

    /**
     * Generic data storage method (for future extensibility)
     *
     * @param int $user_id User ID
     * @param string $data_type Data type (business, realizace, etc.)
     * @param array $data Data to store
     * @return bool Success status
     */
    public function store_user_data(int $user_id, string $data_type, array $data): bool {
        $meta_key = $this->get_meta_key_for_data_type($data_type);
        
        if (!$meta_key) {
            return false;
        }
        
        return update_user_meta($user_id, $meta_key, $data) !== false;
    }

    /**
     * Generic data retrieval method (for future extensibility)
     *
     * @param int $user_id User ID
     * @param string $data_type Data type (business, realizace, etc.)
     * @return array|null Data or null if not found
     */
    public function get_user_data(int $user_id, string $data_type): ?array {
        $meta_key = $this->get_meta_key_for_data_type($data_type);
        
        if (!$meta_key) {
            return null;
        }
        
        $data = get_user_meta($user_id, $meta_key, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Get meta key for data type
     *
     * @param string $data_type Data type
     * @return string|null Meta key or null if invalid type
     */
    private function get_meta_key_for_data_type(string $data_type): ?string {
        return match ($data_type) {
            self::DATA_TYPE_BUSINESS => self::BUSINESS_DATA_META_KEY,
            self::DATA_TYPE_REALIZACE => self::REALIZACE_DATA_META_KEY,
            default => null
        };
    }

    /**
     * Get all supported data types
     *
     * @return array Supported data types
     */
    public function get_supported_data_types(): array {
        return [
            self::DATA_TYPE_BUSINESS,
            self::DATA_TYPE_REALIZACE
        ];
    }

    /**
     * Check if user has specific data type
     *
     * @param int $user_id User ID
     * @param string $data_type Data type
     * @return bool True if user has data of this type
     */
    public function has_user_data(int $user_id, string $data_type): bool {
        return $this->get_user_data($user_id, $data_type) !== null;
    }

    /**
     * Delete specific data type for user
     *
     * @param int $user_id User ID
     * @param string $data_type Data type
     * @return bool Success status
     */
    public function delete_user_data(int $user_id, string $data_type): bool {
        $meta_key = $this->get_meta_key_for_data_type($data_type);
        
        if (!$meta_key) {
            return false;
        }
        
        return delete_user_meta($user_id, $meta_key);
    }

    /**
     * Get users with specific data type
     *
     * @param string $data_type Data type
     * @return array Array of user IDs
     */
    public function get_users_with_data_type(string $data_type): array {
        $meta_key = $this->get_meta_key_for_data_type($data_type);
        
        if (!$meta_key) {
            return [];
        }
        
        global $wpdb;
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $meta_key
        ));
        
        return array_map('intval', $user_ids);
    }
}