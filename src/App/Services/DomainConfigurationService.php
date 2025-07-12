<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Domain Configuration Service
 *
 * Centralized configuration management for all domain-specific settings.
 * Eliminates hardcoded values and provides single source of truth for:
 * - Asset management configurations
 * - Status management settings
 * - Default values and business rules
 * - Localization strings
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DomainConfigurationService
{
    /**
     * Domain configuration registry
     * Stores all domain-specific settings in structured format
     */
    private static array $configurations = [];

    /**
     * Track initialization state
     */
    private static bool $initialized = false;

    /**
     * Initialize domain configurations
     * Called once during bootstrap to register all domain settings
     */
    public static function initialize(): void
    {
        // Guard against double initialization
        if (self::$initialized) {
            return;
        }

        self::registerRealizaceConfiguration();
        self::registerFakturyConfiguration();
        self::$initialized = true;
    }

    /**
     * Register Realizace domain configuration
     */
    private static function registerRealizaceConfiguration(): void
    {
        self::$configurations['realization'] = [
            'post_type' => 'realization',
            'display_name' => 'Realizace',
            'default_points' => 2500,
            'status_config' => [
                'rejected_status' => 'rejected',
                'rejected_label' => 'Odmítnuto'
            ],
            'asset_config' => [
                'script_handle_prefix' => 'realization',
                'localization_object_name' => 'mistrRealizaceAdmin',
                'admin_screen_ids' => ['user-edit', 'profile', 'users', 'post', 'realizace']
            ],
            'field_names' => [
                'points' => 'admin_management_realization_points_assigned',
                'rejection_reason' => 'admin_management_realization_rejection_reason',
                'gallery' => 'realization_gallery',
                'area' => 'realization_area_sqm',
                'construction_type' => 'realization_construction_type',
                'materials' => 'realization_materials_used',
                'awarded_points_meta' => '_realization_points_awarded'
            ],
            'field_selectors' => [
                'rejection_reason' => 'admin_management_realization_rejection_reason',
                'points' => 'admin_management_realization_points_assigned',
                'gallery' => 'realization_gallery',
                'area' => 'realization_area_sqm',
                'construction_type' => 'realization_construction_type',
                'materials' => 'realization_materials_used'
            ],
            'messages' => [
                'confirm_approve' => __('Opravdu chcete schválit tuto realizaci?', 'mistr-fachman'),
                'confirm_reject' => __('Opravdu chcete odmítnout tuto realizaci?', 'mistr-fachman'),
                'confirm_bulk_approve' => __('Opravdu chcete hromadně schválit všechny čekající realizace tohoto uživatele?', 'mistr-fachman')
            ]
        ];
    }

    /**
     * Register Faktury domain configuration
     */
    private static function registerFakturyConfiguration(): void
    {
        self::$configurations['invoice'] = [
            'post_type' => 'invoice',
            'display_name' => 'Faktura',
            'default_points' => 100, // Different default for invoices
            'calculation_rule' => 'floor_division_10', // Dynamic calculation rule
            'status_config' => [
                'rejected_status' => 'rejected',
                'rejected_label' => 'Odmítnuto'
            ],
            'asset_config' => [
                'script_handle_prefix' => 'invoice',
                'localization_object_name' => 'mistrFakturyAdmin',
                'admin_screen_ids' => ['user-edit', 'profile', 'users', 'post', 'faktura']
            ],
            'field_names' => [
                'points' => 'admin_management_invoice_points_assigned',
                'rejection_reason' => 'admin_management_invoice_rejection_reason',
                'value' => 'invoice_value',
                'file' => 'invoice_file',
                'invoice_number' => 'invoice_number',
                'invoice_date' => 'invoice_date',
                'awarded_points_meta' => '_invoice_points_awarded',
                'value_field_key' => 'field_invoice_value_2025',
                'invoice_date_field_key' => 'field_invoice_date_2025'
            ],
            'column_keys' => [
                'invoice_value' => 'invoice_value',
                'invoice_date' => 'invoice_date',
                'invoice_file' => 'invoice_file',
                'invoice_number' => 'invoice_number'
            ],
            'field_selectors' => [
                'rejection_reason' => 'admin_management_invoice_rejection_reason',
                'points' => 'admin_management_invoice_points_assigned',
                'invoice_number' => 'invoice_number',
                'value' => 'invoice_value',
                'file' => 'invoice_file',
                'invoice_date' => 'invoice_date'
            ],
            'messages' => [
                'confirm_approve' => __('Opravdu chcete schválit tuto fakturu?', 'mistr-fachman'),
                'confirm_reject' => __('Opravdu chcete odmítnout tuto fakturu?', 'mistr-fachman'),
                'confirm_bulk_approve' => __('Opravdu chcete hromadně schválit všechny čekající faktury tohoto uživatele?', 'mistr-fachman')
            ]
        ];
    }

    /**
     * Get complete configuration for a domain
     *
     * @param string $domain_key Domain identifier (realization, invoice)
     * @return array Complete domain configuration
     * @throws \InvalidArgumentException If domain not found
     */
    public static function getDomainConfig(string $domain_key): array
    {
        if (!isset(self::$configurations[$domain_key])) {
            throw new \InvalidArgumentException("Domain configuration not found: {$domain_key}");
        }

        return self::$configurations[$domain_key];
    }

    /**
     * Get asset management configuration for a domain
     *
     * @param string $domain_key Domain identifier
     * @return array Asset configuration array for AdminAssetManager
     */
    public static function getAssetConfig(string $domain_key): array
    {
        $config = self::getDomainConfig($domain_key);
        
        return [
            'script_handle_prefix' => $config['asset_config']['script_handle_prefix'],
            'localization_object_name' => $config['asset_config']['localization_object_name'],
            'admin_screen_ids' => $config['asset_config']['admin_screen_ids'],
            'post_type' => $config['post_type'],
            'wordpress_post_type' => $config['post_type'],
            'domain_localization_data' => [
                'field_names' => $config['field_selectors'],
                'default_values' => [
                    'points' => $config['default_points']
                ],
                'messages' => $config['messages']
            ]
        ];
    }

    /**
     * Get status management configuration for a domain
     *
     * @param string $domain_key Domain identifier
     * @return array Status configuration for StatusManager
     */
    public static function getStatusConfig(string $domain_key): array
    {
        $config = self::getDomainConfig($domain_key);
        
        return [
            'post_type' => $config['post_type'],
            'rejected_status' => $config['status_config']['rejected_status'],
            'rejected_label' => $config['status_config']['rejected_label']
        ];
    }

    /**
     * Get field selector for a specific field in a domain
     *
     * @param string $domain_key Domain identifier
     * @param string $field_key Field key (points, rejection_reason, etc.)
     * @return string Field selector
     * @throws \InvalidArgumentException If field not found
     */
    public static function getFieldSelector(string $domain_key, string $field_key): string
    {
        $config = self::getDomainConfig($domain_key);
        
        if (!isset($config['field_selectors'][$field_key])) {
            throw new \InvalidArgumentException("Field selector not found: {$domain_key}.{$field_key}");
        }

        return $config['field_selectors'][$field_key];
    }

    /**
     * Get field name for a specific field in a domain
     *
     * @param string $domain_key Domain identifier
     * @param string $field_key Field key (points, rejection_reason, etc.)
     * @return string Field name
     * @throws \InvalidArgumentException If field not found
     */
    public static function getFieldName(string $domain_key, string $field_key): string
    {
        $config = self::getDomainConfig($domain_key);
        
        if (!isset($config['field_names'][$field_key])) {
            throw new \InvalidArgumentException("Field name not found: {$domain_key}.{$field_key}");
        }

        return $config['field_names'][$field_key];
    }

    /**
     * Get column key for a specific column in a domain
     *
     * @param string $domain_key Domain identifier
     * @param string $column_key Column key (invoice_value, invoice_date, etc.)
     * @return string Column key
     * @throws \InvalidArgumentException If column not found
     */
    public static function getColumnKey(string $domain_key, string $column_key): string
    {
        $config = self::getDomainConfig($domain_key);
        
        if (!isset($config['column_keys'][$column_key])) {
            throw new \InvalidArgumentException("Column key not found: {$domain_key}.{$column_key}");
        }

        return $config['column_keys'][$column_key];
    }

    /**
     * Get default points value for a domain
     *
     * @param string $domain_key Domain identifier
     * @return int Default points value
     */
    public static function getDefaultPoints(string $domain_key): int
    {
        $config = self::getDomainConfig($domain_key);
        return $config['default_points'];
    }

    /**
     * Get display name for a domain
     *
     * @param string $domain_key Domain identifier
     * @return string Human-readable domain name
     */
    public static function getDisplayName(string $domain_key): string
    {
        $config = self::getDomainConfig($domain_key);
        return $config['display_name'];
    }

    /**
     * Get WordPress post type for a domain
     *
     * @param string $domain_key Domain identifier
     * @return string WordPress post type slug
     */
    public static function getWordPressPostType(string $domain_key): string
    {
        $config = self::getDomainConfig($domain_key);
        return $config['post_type'];
    }

    /**
     * Get all registered domain keys
     *
     * @return array List of domain identifiers
     */
    public static function getRegisteredDomains(): array
    {
        return array_keys(self::$configurations);
    }

    /**
     * Check if a domain has a specific configuration key
     *
     * @param string $domain_key Domain identifier
     * @param string $config_key Configuration key to check
     * @return bool True if configuration exists
     */
    public static function hasConfig(string $domain_key, string $config_key): bool
    {
        try {
            $config = self::getDomainConfig($domain_key);
            return isset($config[$config_key]);
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Get domain configuration value by key path
     *
     * @param string $domain_key Domain identifier
     * @param string $key_path Dot-notation path (e.g., 'asset_config.script_handle_prefix')
     * @return mixed Configuration value
     * @throws \InvalidArgumentException If path not found
     */
    public static function getConfigValue(string $domain_key, string $key_path)
    {
        $config = self::getDomainConfig($domain_key);
        $keys = explode('.', $key_path);
        $value = $config;

        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                throw new \InvalidArgumentException("Configuration path not found: {$domain_key}.{$key_path}");
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * User workflow progress constants
     */
    public const PROGRESS_INITIAL_REGISTRATION = 33;
    public const PROGRESS_FORM_SUBMITTED = 66;
    public const PROGRESS_APPROVED = 100;
    public const PROGRESS_PROJECT_UPLOADED = 100;
    
    /**
     * User workflow reward constants
     */
    public const REWARD_REGISTRATION_COMPLETED = 1000;
    public const REWARD_FIRST_PROJECT_UPLOADED = 2500;

    /**
     * Get user workflow progress percentage
     *
     * @param string $status User status (needs_form, awaiting_review, full_member)
     * @return int Progress percentage
     */
    public static function getUserProgressPercentage(string $status): int
    {
        switch ($status) {
            case 'needs_form':
                return self::PROGRESS_INITIAL_REGISTRATION;
            case 'awaiting_review':
                return self::PROGRESS_FORM_SUBMITTED;
            case 'full_member':
                return self::PROGRESS_APPROVED;
            default:
                return 0;
        }
    }

    /**
     * Get user workflow reward points
     *
     * @param string $milestone Milestone identifier (registration_completed)
     * @return int Reward points
     */
    public static function getUserWorkflowReward(string $milestone): int
    {
        switch ($milestone) {
            case 'registration_completed':
                return self::REWARD_REGISTRATION_COMPLETED;
            case 'first_project_uploaded':
                return self::REWARD_FIRST_PROJECT_UPLOADED;
            default:
                return 0;
        }
    }
}