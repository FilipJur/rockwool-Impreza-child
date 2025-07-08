<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Points Calculation Service
 *
 * Centralized business logic for all point calculations across domains.
 * Eliminates hardcoded calculation formulas and provides single source of truth for:
 * - Domain-specific point calculation rules
 * - Business rule validation
 * - Dynamic calculation logic
 * - Fixed point values
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PointsCalculationService
{
    /**
     * Calculation rule constants
     */
    public const RULE_FIXED_VALUE = 'fixed_value';
    public const RULE_FLOOR_DIVISION_10 = 'floor_division_10';
    public const RULE_PERCENTAGE = 'percentage';
    public const RULE_DYNAMIC = 'dynamic';

    /**
     * Calculate points for a domain and post
     *
     * @param string $domain_key Domain identifier (realization, invoice)
     * @param int $post_id Post ID for dynamic calculations
     * @return int Calculated points
     * @throws \InvalidArgumentException If domain or calculation rule invalid
     */
    public static function calculatePoints(string $domain_key, int $post_id = 0): int
    {
        $config = DomainConfigurationService::getDomainConfig($domain_key);
        
        // Get calculation rule (default to fixed value)
        $calculation_rule = $config['calculation_rule'] ?? self::RULE_FIXED_VALUE;
        
        return match ($calculation_rule) {
            self::RULE_FIXED_VALUE => self::calculateFixedValue($config),
            self::RULE_FLOOR_DIVISION_10 => self::calculateFloorDivision10($domain_key, $post_id),
            self::RULE_PERCENTAGE => self::calculatePercentage($domain_key, $post_id, $config),
            self::RULE_DYNAMIC => self::calculateDynamic($domain_key, $post_id),
            default => throw new \InvalidArgumentException("Unknown calculation rule: {$calculation_rule}")
        };
    }

    /**
     * Calculate fixed point value
     * Used for domains with static point awards (e.g., Realizace)
     *
     * @param array $config Domain configuration
     * @return int Fixed points value
     */
    private static function calculateFixedValue(array $config): int
    {
        return $config['default_points'] ?? 0;
    }

    /**
     * Calculate points using floor division by 10
     * Used for invoice calculations: floor(value / 10)
     * Business rule: 10 CZK = 1 point
     *
     * @param string $domain_key Domain identifier
     * @param int $post_id Post ID
     * @return int Calculated points
     */
    private static function calculateFloorDivision10(string $domain_key, int $post_id): int
    {
        if ($post_id === 0) {
            return 0; // No post ID provided
        }

        // Use domain-specific field service to get value
        $value = self::getFieldValue($domain_key, 'value', $post_id);
        
        if ($value <= 0) {
            return 0; // No valid value
        }

        // Core business logic: floor(value / 10) - 10 CZK = 1 point
        return (int) floor($value / 10);
    }

    /**
     * Calculate points using percentage
     * Future use for percentage-based calculations
     *
     * @param string $domain_key Domain identifier
     * @param int $post_id Post ID
     * @param array $config Domain configuration
     * @return int Calculated points
     */
    private static function calculatePercentage(string $domain_key, int $post_id, array $config): int
    {
        $percentage = $config['percentage'] ?? 1.0;
        $base_value = self::getFieldValue($domain_key, 'value', $post_id);
        
        return (int) round($base_value * $percentage);
    }

    /**
     * Calculate points using dynamic logic
     * Future use for complex business rules
     *
     * @param string $domain_key Domain identifier
     * @param int $post_id Post ID
     * @return int Calculated points
     */
    private static function calculateDynamic(string $domain_key, int $post_id): int
    {
        // Future implementation for complex calculations
        // Could include factors like user tier, seasonal bonuses, etc.
        return self::calculateFixedValue(DomainConfigurationService::getDomainConfig($domain_key));
    }

    /**
     * Get field value using domain-specific field service
     *
     * @param string $domain_key Domain identifier
     * @param string $field_key Field key (value, points, etc.)
     * @param int $post_id Post ID
     * @return int Field value
     */
    private static function getFieldValue(string $domain_key, string $field_key, int $post_id): int
    {
        // Use appropriate field service based on domain
        return match ($domain_key) {
            'realization' => self::getRealizaceFieldValue($field_key, $post_id),
            'invoice' => self::getFakturaFieldValue($field_key, $post_id),
            default => 0
        };
    }

    /**
     * Get Realizace field value
     *
     * @param string $field_key Field key
     * @param int $post_id Post ID
     * @return int Field value
     */
    private static function getRealizaceFieldValue(string $field_key, int $post_id): int
    {
        return match ($field_key) {
            'points' => \MistrFachman\Realizace\RealizaceFieldService::getPoints($post_id),
            'value' => 0, // Realizace doesn't have value field
            default => 0
        };
    }

    /**
     * Get Faktura field value
     *
     * @param string $field_key Field key
     * @param int $post_id Post ID
     * @return int Field value
     */
    private static function getFakturaFieldValue(string $field_key, int $post_id): int
    {
        return match ($field_key) {
            'points' => \MistrFachman\Faktury\FakturaFieldService::getPoints($post_id),
            'value' => \MistrFachman\Faktury\FakturaFieldService::getValue($post_id),
            default => 0
        };
    }

    /**
     * Validate calculation rule for a domain
     *
     * @param string $domain_key Domain identifier
     * @param string $rule Calculation rule to validate
     * @return bool True if rule is valid for domain
     */
    public static function isValidRuleForDomain(string $domain_key, string $rule): bool
    {
        $valid_rules = match ($domain_key) {
            'realization' => [self::RULE_FIXED_VALUE],
            'invoice' => [self::RULE_FIXED_VALUE, self::RULE_FLOOR_DIVISION_10, self::RULE_PERCENTAGE],
            default => [self::RULE_FIXED_VALUE]
        };

        return in_array($rule, $valid_rules, true);
    }

    /**
     * Get all available calculation rules
     *
     * @return array<string, string> Rule constant => description
     */
    public static function getAvailableRules(): array
    {
        return [
            self::RULE_FIXED_VALUE => 'Fixed Value (static points)',
            self::RULE_FLOOR_DIVISION_10 => 'Floor Division by 10 (invoice formula)',
            self::RULE_PERCENTAGE => 'Percentage of Value',
            self::RULE_DYNAMIC => 'Dynamic Calculation (complex rules)'
        ];
    }

    /**
     * Get calculation rule for a domain
     *
     * @param string $domain_key Domain identifier
     * @return string Calculation rule constant
     */
    public static function getRuleForDomain(string $domain_key): string
    {
        try {
            return DomainConfigurationService::getConfigValue($domain_key, 'calculation_rule');
        } catch (\InvalidArgumentException $e) {
            // Default to fixed value if no rule specified
            return self::RULE_FIXED_VALUE;
        }
    }

    /**
     * Preview points calculation without executing
     * Useful for admin interfaces and validation
     *
     * @param string $domain_key Domain identifier
     * @param int $post_id Post ID
     * @param array $override_values Optional values to override (for previews)
     * @return array Calculation details [points, rule, base_value, formula]
     */
    public static function previewCalculation(string $domain_key, int $post_id = 0, array $override_values = []): array
    {
        $config = DomainConfigurationService::getDomainConfig($domain_key);
        $rule = $config['calculation_rule'] ?? self::RULE_FIXED_VALUE;
        
        $base_value = $override_values['value'] ?? self::getFieldValue($domain_key, 'value', $post_id);
        $default_points = $config['default_points'] ?? 0;
        
        $preview = [
            'rule' => $rule,
            'base_value' => $base_value,
            'default_points' => $default_points,
            'formula' => self::getFormulaDescription($rule),
            'points' => 0
        ];
        
        $preview['points'] = match ($rule) {
            self::RULE_FIXED_VALUE => $default_points,
            self::RULE_FLOOR_DIVISION_10 => $base_value > 0 ? (int) floor($base_value / 10) : 0,
            self::RULE_PERCENTAGE => (int) round($base_value * ($config['percentage'] ?? 1.0)),
            default => $default_points
        };
        
        return $preview;
    }

    /**
     * Get human-readable formula description
     *
     * @param string $rule Calculation rule
     * @return string Formula description
     */
    private static function getFormulaDescription(string $rule): string
    {
        return match ($rule) {
            self::RULE_FIXED_VALUE => 'Fixed value (no calculation)',
            self::RULE_FLOOR_DIVISION_10 => 'floor(value ÷ 10) - 10 CZK = 1 bod',
            self::RULE_PERCENTAGE => 'value × percentage',
            self::RULE_DYNAMIC => 'Complex business rules',
            default => 'Unknown formula'
        };
    }
}