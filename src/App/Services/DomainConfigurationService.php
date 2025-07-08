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
            'wordpress_post_type' => 'realizace',
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
            'field_selectors' => [
                'rejection_reason' => \MistrFachman\Realizace\RealizaceFieldService::getRejectionReasonFieldSelector(),
                'points' => \MistrFachman\Realizace\RealizaceFieldService::getPointsFieldSelector(),
                'gallery' => \MistrFachman\Realizace\RealizaceFieldService::getGalleryFieldSelector(),
                'area' => \MistrFachman\Realizace\RealizaceFieldService::getAreaFieldSelector(),
                'construction_type' => \MistrFachman\Realizace\RealizaceFieldService::getConstructionTypeFieldSelector(),
                'materials' => \MistrFachman\Realizace\RealizaceFieldService::getMaterialsFieldSelector()
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
            'wordpress_post_type' => 'faktura',
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
            'field_selectors' => [
                'rejection_reason' => \MistrFachman\Faktury\FakturaFieldService::getRejectionReasonFieldSelector(),
                'points' => \MistrFachman\Faktury\FakturaFieldService::getPointsFieldSelector(),
                'invoice_number' => \MistrFachman\Faktury\FakturaFieldService::getInvoiceNumberFieldSelector(),
                'value' => \MistrFachman\Faktury\FakturaFieldService::getValueFieldSelector(),
                'file' => \MistrFachman\Faktury\FakturaFieldService::getFileFieldSelector(),
                'invoice_date' => \MistrFachman\Faktury\FakturaFieldService::getInvoiceDateFieldSelector()
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
            'wordpress_post_type' => $config['wordpress_post_type'],
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
        return $config['wordpress_post_type'];
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
}