<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Domain Registry Service - Centralized Domain Configuration
 *
 * Solves Czech language singular/plural naming mismatches by providing
 * a single source of truth for all domain-related identifiers.
 *
 * PROBLEM: Czech has different singular/plural forms:
 * - realizace = realizace (same form, no issue)
 * - faktura ≠ faktury (different forms, causes AJAX/nonce mismatches)
 * - certifikát ≠ certifikáty (future domain issue)
 *
 * SOLUTION: Central registry maps all identifiers consistently
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DomainRegistry {

    /**
     * Domain configuration registry
     * 
     * Each domain has consistent mapping of all identifiers:
     * - post_type: WordPress post type (usually singular)
     * - script_prefix: JavaScript/CSS prefix (can be plural for UI)
     * - js_slug: JavaScript domain slug (for CSS classes, endpoints)
     * - display_name: Human-readable name (for UI)
     */
    private static array $domains = [
        'realizace' => [
            'post_type' => 'realizace',
            'script_prefix' => 'realizace',
            'js_slug' => 'realizace',
            'display_name' => 'Realizace',
            'display_name_plural' => 'Realizace'
        ],
        'faktury' => [
            'post_type' => 'faktura',        // WordPress post type (singular)
            'script_prefix' => 'faktury',    // Script handle prefix (plural)
            'js_slug' => 'faktury',          // JavaScript domain slug (plural)
            'display_name' => 'Faktura',     // Display name (singular)
            'display_name_plural' => 'Faktury' // Display name (plural)
        ],
        // Future domains with Czech plural/singular differences
        'certifikaty' => [
            'post_type' => 'certifikat',
            'script_prefix' => 'certifikaty',
            'js_slug' => 'certifikaty',
            'display_name' => 'Certifikát',
            'display_name_plural' => 'Certifikáty'
        ]
    ];

    /**
     * Get WordPress post type for a domain
     *
     * @param string $domain Domain key
     * @return string Post type (used for AJAX hooks, database queries)
     * @throws \InvalidArgumentException If domain not found
     */
    public static function getPostType(string $domain): string {
        return self::getDomainConfig($domain)['post_type'];
    }

    /**
     * Get script handle prefix for a domain
     *
     * @param string $domain Domain key
     * @return string Script prefix (used for CSS/JS handles)
     * @throws \InvalidArgumentException If domain not found
     */
    public static function getScriptPrefix(string $domain): string {
        return self::getDomainConfig($domain)['script_prefix'];
    }

    /**
     * Get JavaScript slug for a domain
     *
     * @param string $domain Domain key
     * @return string JS slug (used for CSS classes, frontend identifiers)
     * @throws \InvalidArgumentException If domain not found
     */
    public static function getJsSlug(string $domain): string {
        return self::getDomainConfig($domain)['js_slug'];
    }

    /**
     * Get display name for a domain
     *
     * @param string $domain Domain key
     * @param bool $plural Whether to return plural form
     * @return string Human-readable display name
     * @throws \InvalidArgumentException If domain not found
     */
    public static function getDisplayName(string $domain, bool $plural = false): string {
        $config = self::getDomainConfig($domain);
        return $plural ? $config['display_name_plural'] : $config['display_name'];
    }

    /**
     * Get AJAX action prefix for a domain
     * 
     * CRITICAL: Always uses post_type to ensure AJAX hooks match
     * This prevents nonce mismatches between PHP and JavaScript
     *
     * @param string $domain Domain key
     * @return string AJAX prefix (always based on post_type)
     * @throws \InvalidArgumentException If domain not found
     */
    public static function getAjaxPrefix(string $domain): string {
        return self::getPostType($domain);
    }

    /**
     * Get complete domain configuration
     *
     * @param string $domain Domain key
     * @return array Complete domain configuration
     * @throws \InvalidArgumentException If domain not found
     */
    public static function getDomainConfig(string $domain): array {
        if (!isset(self::$domains[$domain])) {
            throw new \InvalidArgumentException("Domain '{$domain}' not found in registry");
        }

        return self::$domains[$domain];
    }

    /**
     * Get all registered domains
     *
     * @return array All domain configurations
     */
    public static function getAllDomains(): array {
        return self::$domains;
    }

    /**
     * Check if domain is registered
     *
     * @param string $domain Domain key to check
     * @return bool True if domain exists
     */
    public static function isDomainRegistered(string $domain): bool {
        return isset(self::$domains[$domain]);
    }

    /**
     * Validate domain configuration consistency
     * 
     * Checks for common Czech language pitfalls and configuration errors
     *
     * @param string $domain Domain key to validate
     * @return array Validation results ['valid' => bool, 'warnings' => array]
     */
    public static function validateDomain(string $domain): array {
        if (!self::isDomainRegistered($domain)) {
            return [
                'valid' => false,
                'warnings' => ["Domain '{$domain}' not registered"]
            ];
        }

        $config = self::$domains[$domain];
        $warnings = [];

        // Check for common Czech plural/singular mismatches
        $post_type = $config['post_type'];
        $js_slug = $config['js_slug'];

        // Known problematic patterns
        $czech_plurals = [
            'faktura' => 'faktury',
            'certifikat' => 'certifikaty',
            'lokalizace' => 'lokalizace' // Same form - OK
        ];

        if (isset($czech_plurals[$post_type]) && $czech_plurals[$post_type] !== $js_slug) {
            if ($js_slug === $post_type) {
                $warnings[] = "JavaScript slug uses singular form '{$js_slug}' - consider plural '{$czech_plurals[$post_type]}' for UI consistency";
            }
        }

        // Ensure post_type is used for AJAX consistency
        $ajax_prefix = self::getAjaxPrefix($domain);
        if ($ajax_prefix !== $post_type) {
            $warnings[] = "AJAX prefix mismatch detected - this will cause nonce failures";
        }

        return [
            'valid' => empty($warnings) || count($warnings) === 1, // UI warnings are OK
            'warnings' => $warnings
        ];
    }

    /**
     * Generate AJAX action name for a domain
     *
     * @param string $domain Domain key
     * @param string $action Action type (e.g., 'quick_action', 'bulk_approve')
     * @return string Complete AJAX action name
     */
    public static function getAjaxAction(string $domain, string $action): string {
        $prefix = self::getAjaxPrefix($domain);
        
        switch ($action) {
            case 'quick_action':
                return "mistr_fachman_{$prefix}_quick_action";
            case 'bulk_approve':
                return "mistr_fachman_bulk_approve_{$prefix}";
            default:
                return "mistr_fachman_{$prefix}_{$action}";
        }
    }

    /**
     * Generate nonce action name for a domain
     *
     * @param string $domain Domain key
     * @param string $action Action type
     * @return string Nonce action name
     */
    public static function getNonceAction(string $domain, string $action): string {
        $prefix = self::getAjaxPrefix($domain);
        
        switch ($action) {
            case 'quick_action':
                return "mistr_fachman_{$prefix}_action";
            case 'bulk_approve':
                return "mistr_fachman_bulk_approve_{$prefix}";
            default:
                return "mistr_fachman_{$prefix}_{$action}";
        }
    }

    /**
     * Get domain key by post type (reverse lookup)
     *
     * @param string $post_type WordPress post type
     * @return string|null Domain key or null if not found
     */
    public static function getDomainByPostType(string $post_type): ?string {
        foreach (self::$domains as $domain => $config) {
            if ($config['post_type'] === $post_type) {
                return $domain;
            }
        }
        return null;
    }

    /**
     * Get localization data for JavaScript
     * 
     * Provides all domain identifiers for frontend use
     *
     * @param string $domain Domain key
     * @return array Localization data for JavaScript
     */
    public static function getJsLocalizationData(string $domain): array {
        $config = self::getDomainConfig($domain);
        
        return [
            'domain' => $domain,
            'postType' => $config['post_type'],
            'jsSlug' => $config['js_slug'],
            'displayName' => $config['display_name'],
            'displayNamePlural' => $config['display_name_plural'],
            'ajaxActions' => [
                'quickAction' => self::getAjaxAction($domain, 'quick_action'),
                'bulkApprove' => self::getAjaxAction($domain, 'bulk_approve')
            ],
            'nonceActions' => [
                'quickAction' => self::getNonceAction($domain, 'quick_action'),
                'bulkApprove' => self::getNonceAction($domain, 'bulk_approve')
            ]
        ];
    }
}