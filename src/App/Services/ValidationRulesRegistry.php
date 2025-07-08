<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Validation Rules Registry
 *
 * Centralized registry for all domain-specific validation rules.
 * Eliminates scattered validation logic and provides single source of truth for:
 * - Domain-specific validation requirements
 * - Business rule enforcement
 * - Error message standardization
 * - Validation rule composition
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ValidationRulesRegistry
{
    /**
     * Validation rule types
     */
    public const RULE_REQUIRED = 'required';
    public const RULE_DATE_CURRENT_YEAR = 'date_current_year';
    public const RULE_NUMERIC_POSITIVE = 'numeric_positive';
    public const RULE_FILE_REQUIRED = 'file_required';
    public const RULE_MONTHLY_LIMIT = 'monthly_limit';
    public const RULE_UNIQUE_INVOICE_NUMBER = 'unique_invoice_number';
    public const RULE_MIN_LENGTH = 'min_length';
    public const RULE_MAX_LENGTH = 'max_length';

    /**
     * Validation rules registry by domain
     */
    private static array $rules = [];

    /**
     * Track initialization state
     */
    private static bool $initialized = false;

    /**
     * Initialize validation rules for all domains
     */
    public static function initialize(): void
    {
        // Guard against double initialization
        if (self::$initialized) {
            return;
        }

        self::registerRealizaceRules();
        self::registerFakturyRules();
        self::$initialized = true;
    }

    /**
     * Register validation rules for Realizace domain
     */
    private static function registerRealizaceRules(): void
    {
        self::$rules['realization'] = [
            'pre_publish' => [
                // Realizace currently has no pre-publish validation rules
                // Future rules can be added here (e.g., gallery required, area validation)
            ],
            'field_validation' => [
                'points' => [
                    self::RULE_NUMERIC_POSITIVE => [
                        'message' => __('Body musí být kladné číslo.', 'mistr-fachman')
                    ]
                ],
                'area' => [
                    self::RULE_NUMERIC_POSITIVE => [
                        'message' => __('Plocha musí být kladné číslo.', 'mistr-fachman')
                    ]
                ]
            ]
        ];
    }

    /**
     * Register validation rules for Faktury domain
     */
    private static function registerFakturyRules(): void
    {
        self::$rules['invoice'] = [
            'pre_publish' => [
                'invoice_date' => [
                    self::RULE_REQUIRED => [
                        'message' => __('Datum faktury je povinné.', 'mistr-fachman')
                    ],
                    self::RULE_DATE_CURRENT_YEAR => [
                        'message' => __('Faktura musí být z aktuálního roku (%s).', 'mistr-fachman'),
                        'params' => [date('Y')]
                    ]
                ],
                'invoice_value' => [
                    self::RULE_REQUIRED => [
                        'message' => __('Hodnota faktury je povinná.', 'mistr-fachman')
                    ],
                    self::RULE_NUMERIC_POSITIVE => [
                        'message' => __('Hodnota faktury musí být kladné číslo.', 'mistr-fachman')
                    ]
                ],
                'monthly_limit' => [
                    self::RULE_MONTHLY_LIMIT => [
                        'message' => __('Překročen měsíční limit faktur. Maximálně %d faktur za měsíc, aktuálně: %d.', 'mistr-fachman'),
                        'limit' => 10 // Monthly limit
                    ]
                ]
            ],
            'field_validation' => [
                'invoice_number' => [
                    self::RULE_UNIQUE_INVOICE_NUMBER => [
                        'message' => __('Číslo faktury již existuje.', 'mistr-fachman')
                    ],
                    self::RULE_MIN_LENGTH => [
                        'message' => __('Číslo faktury musí mít alespoň %d znaků.', 'mistr-fachman'),
                        'length' => 3
                    ]
                ],
                'points' => [
                    self::RULE_NUMERIC_POSITIVE => [
                        'message' => __('Body musí být kladné číslo.', 'mistr-fachman')
                    ]
                ]
            ]
        ];
    }

    /**
     * Get validation rules for a domain and context
     *
     * @param string $domain_key Domain identifier (realization, invoice)
     * @param string $context Validation context (pre_publish, field_validation)
     * @return array Validation rules
     * @throws \InvalidArgumentException If domain or context not found
     */
    public static function getRules(string $domain_key, string $context): array
    {
        if (!isset(self::$rules[$domain_key])) {
            throw new \InvalidArgumentException("Validation rules not found for domain: {$domain_key}");
        }

        if (!isset(self::$rules[$domain_key][$context])) {
            return []; // No rules for this context
        }

        return self::$rules[$domain_key][$context];
    }

    /**
     * Get validation rules for a specific field
     *
     * @param string $domain_key Domain identifier
     * @param string $field_key Field name
     * @return array Field validation rules
     */
    public static function getFieldRules(string $domain_key, string $field_key): array
    {
        $field_rules = self::getRules($domain_key, 'field_validation');
        return $field_rules[$field_key] ?? [];
    }

    /**
     * Validate a field value against its rules
     *
     * @param string $domain_key Domain identifier
     * @param string $field_key Field name
     * @param mixed $value Field value
     * @param array $context Additional context data
     * @return array Validation result [success => bool, errors => array]
     */
    public static function validateField(string $domain_key, string $field_key, $value, array $context = []): array
    {
        $rules = self::getFieldRules($domain_key, $field_key);
        $errors = [];

        foreach ($rules as $rule_type => $rule_config) {
            $validation_result = self::applyValidationRule($rule_type, $value, $rule_config, $context);
            
            if (!$validation_result['success']) {
                $errors[] = $validation_result['message'];
            }
        }

        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate pre-publish rules for a domain
     *
     * @param string $domain_key Domain identifier
     * @param \WP_Post $post Post object
     * @param array $form_data Additional form data
     * @return array Validation result [success => bool, message => string]
     */
    public static function validatePrePublish(string $domain_key, \WP_Post $post, array $form_data = []): array
    {
        $rules = self::getRules($domain_key, 'pre_publish');
        $errors = [];

        foreach ($rules as $field_key => $field_rules) {
            foreach ($field_rules as $rule_type => $rule_config) {
                $value = self::getFieldValueForValidation($domain_key, $field_key, $post, $form_data);
                $context = ['post' => $post, 'form_data' => $form_data];
                
                $validation_result = self::applyValidationRule($rule_type, $value, $rule_config, $context);
                
                if (!$validation_result['success']) {
                    $errors[] = $validation_result['message'];
                }
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => implode(' ', $errors)
            ];
        }

        return ['success' => true];
    }

    /**
     * Apply a specific validation rule
     *
     * @param string $rule_type Rule type constant
     * @param mixed $value Value to validate
     * @param array $rule_config Rule configuration
     * @param array $context Additional context
     * @return array Validation result [success => bool, message => string]
     */
    private static function applyValidationRule(string $rule_type, $value, array $rule_config, array $context): array
    {
        return match ($rule_type) {
            self::RULE_REQUIRED => self::validateRequired($value, $rule_config),
            self::RULE_DATE_CURRENT_YEAR => self::validateDateCurrentYear($value, $rule_config),
            self::RULE_NUMERIC_POSITIVE => self::validateNumericPositive($value, $rule_config),
            self::RULE_MONTHLY_LIMIT => self::validateMonthlyLimit($value, $rule_config, $context),
            self::RULE_UNIQUE_INVOICE_NUMBER => self::validateUniqueInvoiceNumber($value, $rule_config, $context),
            self::RULE_MIN_LENGTH => self::validateMinLength($value, $rule_config),
            self::RULE_MAX_LENGTH => self::validateMaxLength($value, $rule_config),
            default => ['success' => true, 'message' => '']
        };
    }

    /**
     * Validate required field
     */
    private static function validateRequired($value, array $config): array
    {
        $is_valid = !empty($value) || (is_numeric($value) && $value != 0);
        
        return [
            'success' => $is_valid,
            'message' => $is_valid ? '' : $config['message']
        ];
    }

    /**
     * Validate date is from current year
     */
    private static function validateDateCurrentYear($value, array $config): array
    {
        // Sanitize input - remove any non-date characters
        $value = sanitize_text_field($value);
        
        if (empty($value)) {
            return ['success' => false, 'message' => $config['message']];
        }

        $date = \DateTime::createFromFormat('Ymd', $value) ?: \DateTime::createFromFormat('Y-m-d', $value);
        
        if (!$date) {
            return ['success' => false, 'message' => $config['message']];
        }

        $current_year = (int) date('Y');
        $value_year = (int) $date->format('Y');
        
        $is_valid = $value_year === $current_year;
        
        return [
            'success' => $is_valid,
            'message' => $is_valid ? '' : sprintf($config['message'], $current_year)
        ];
    }

    /**
     * Validate positive numeric value
     */
    private static function validateNumericPositive($value, array $config): array
    {
        $is_valid = is_numeric($value) && $value > 0;
        
        return [
            'success' => $is_valid,
            'message' => $is_valid ? '' : $config['message']
        ];
    }

    /**
     * Validate monthly submission limit
     */
    private static function validateMonthlyLimit($value, array $config, array $context): array
    {
        $post = $context['post'] ?? null;
        if (!$post) {
            return ['success' => true, 'message' => ''];
        }

        $user_id = $post->post_author;
        $current_month = date('Y-m');
        
        $monthly_count = get_posts([
            'post_type' => $post->post_type,
            'author' => $user_id,
            'date_query' => [
                [
                    'year' => date('Y'),
                    'month' => date('n')
                ]
            ],
            'post_status' => ['publish', 'pending'],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        $count = count($monthly_count);
        $limit = $config['limit'];
        
        $is_valid = $count < $limit;
        
        return [
            'success' => $is_valid,
            'message' => $is_valid ? '' : sprintf($config['message'], $limit, $count)
        ];
    }

    /**
     * Validate unique invoice number
     */
    private static function validateUniqueInvoiceNumber($value, array $config, array $context): array
    {
        // Sanitize input - invoice numbers should be alphanumeric
        $value = sanitize_text_field($value);
        
        if (empty($value)) {
            return ['success' => true, 'message' => ''];
        }

        $post = $context['post'] ?? null;
        $exclude_id = $post ? $post->ID : 0;

        $existing_posts = get_posts([
            'post_type' => 'faktura',
            'meta_query' => [
                [
                    'key' => 'invoice_number',
                    'value' => $value,
                    'compare' => '='
                ]
            ],
            'exclude' => [$exclude_id],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        $is_valid = empty($existing_posts);
        
        return [
            'success' => $is_valid,
            'message' => $is_valid ? '' : $config['message']
        ];
    }

    /**
     * Validate minimum length
     */
    private static function validateMinLength($value, array $config): array
    {
        // Sanitize input before length check
        $value = sanitize_text_field($value);
        $length = strlen((string) $value);
        $min_length = $config['length'];
        $is_valid = $length >= $min_length;
        
        return [
            'success' => $is_valid,
            'message' => $is_valid ? '' : sprintf($config['message'], $min_length)
        ];
    }

    /**
     * Validate maximum length
     */
    private static function validateMaxLength($value, array $config): array
    {
        // Sanitize input before length check
        $value = sanitize_text_field($value);
        $length = strlen((string) $value);
        $max_length = $config['length'];
        $is_valid = $length <= $max_length;
        
        return [
            'success' => $is_valid,
            'message' => $is_valid ? '' : sprintf($config['message'], $max_length)
        ];
    }

    /**
     * Get field value for validation from various sources
     */
    private static function getFieldValueForValidation(string $domain_key, string $field_key, \WP_Post $post, array $form_data)
    {
        // Try form data first (for fresh submissions)
        if (isset($form_data[$field_key])) {
            return $form_data[$field_key];
        }

        // Try ACF field key format
        $field_selector = DomainConfigurationService::getFieldSelector($domain_key, $field_key);
        if (isset($form_data["acf[{$field_selector}]"])) {
            return $form_data["acf[{$field_selector}]"];
        }

        // Fallback to stored field value
        return match ($domain_key) {
            'realization' => self::getRealizaceFieldValue($field_key, $post->ID),
            'invoice' => self::getFakturaFieldValue($field_key, $post->ID),
            default => ''
        };
    }

    /**
     * Get Realizace field value for validation
     */
    private static function getRealizaceFieldValue(string $field_key, int $post_id)
    {
        return match ($field_key) {
            'points' => \MistrFachman\Realizace\RealizaceFieldService::getPoints($post_id),
            'area' => \MistrFachman\Realizace\RealizaceFieldService::getArea($post_id),
            default => ''
        };
    }

    /**
     * Get Faktura field value for validation
     */
    private static function getFakturaFieldValue(string $field_key, int $post_id)
    {
        return match ($field_key) {
            'invoice_date' => \MistrFachman\Faktury\FakturaFieldService::getInvoiceDate($post_id),
            'invoice_value' => \MistrFachman\Faktury\FakturaFieldService::getValue($post_id),
            'invoice_number' => \MistrFachman\Faktury\FakturaFieldService::getInvoiceNumber($post_id),
            'points' => \MistrFachman\Faktury\FakturaFieldService::getPoints($post_id),
            default => ''
        };
    }

    /**
     * Get all registered domains
     *
     * @return array List of domain identifiers
     */
    public static function getRegisteredDomains(): array
    {
        return array_keys(self::$rules);
    }

    /**
     * Check if domain has validation rules for context
     *
     * @param string $domain_key Domain identifier
     * @param string $context Validation context
     * @return bool True if rules exist
     */
    public static function hasRules(string $domain_key, string $context): bool
    {
        return isset(self::$rules[$domain_key][$context]) && !empty(self::$rules[$domain_key][$context]);
    }
}