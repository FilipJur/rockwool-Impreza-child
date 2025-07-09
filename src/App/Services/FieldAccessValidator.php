<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Field Access Validator
 *
 * Validates codebase compliance with FieldService patterns.
 * Scans for hardcoded field access violations and ensures proper abstraction.
 * Provides both automated scanning and manual validation tools.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FieldAccessValidator
{
    /**
     * Violation patterns to detect
     */
    private const VIOLATION_PATTERNS = [
        'hardcoded_get_field' => [
            'pattern' => '/get_field\s*\(\s*[\'"]([^\'"]+)[\'"]/',
            'message' => 'Hardcoded get_field() call detected. Use FieldService instead.',
            'severity' => 'error'
        ],
        'hardcoded_update_field' => [
            'pattern' => '/update_field\s*\(\s*[\'"]([^\'"]+)[\'"]/',
            'message' => 'Hardcoded update_field() call detected. Use FieldService instead.',
            'severity' => 'error'
        ],
        'hardcoded_post_meta' => [
            'pattern' => '/get_post_meta\s*\(\s*\$[^,]+,\s*[\'"]([^\'"]+)[\'"]/',
            'message' => 'Direct post meta access detected. Use FieldService instead.',
            'severity' => 'warning'
        ],
        'hardcoded_constants' => [
            'pattern' => '/[\'"](?:admin_management_|sprava_a_hodnoceni|realizace_|faktura_|invoice_)[^\'"]*[\'"]/',
            'message' => 'Hardcoded field name constant detected. Use DomainConfigurationService.',
            'severity' => 'error'
        ],
        'hardcoded_points' => [
            'pattern' => '/(?:return\s+|=\s*)(?:2500|100)\s*;/',
            'message' => 'Hardcoded points value detected. Use PointsCalculationService.',
            'severity' => 'error'
        ],
        'hardcoded_calculation' => [
            'pattern' => '/floor\s*\(\s*\$[^\/]+\s*\/\s*10\s*\)/',
            'message' => 'Hardcoded calculation formula detected. Use PointsCalculationService.',
            'severity' => 'error'
        ]
    ];

    /**
     * Allowed patterns (whitelist)
     */
    private const ALLOWED_PATTERNS = [
        'field_service_base' => '/FieldServiceBase\.php$/',
        'configuration_service' => '/DomainConfigurationService\.php$/',
        'points_calculation_service' => '/PointsCalculationService\.php$/',
        'validation_rules_registry' => '/ValidationRulesRegistry\.php$/',
        'field_access_validator' => '/FieldAccessValidator\.php$/',
        'debug_validator' => '/DebugValidator\.php$/',
        'cf7_form_tags' => '/CF7.*Tag\.php$/',
        'taxonomy_manager' => '/TaxonomyManager\.php$/',
    ];

    /**
     * Context-aware exemptions for specific patterns
     */
    private const CONTEXT_EXEMPTIONS = [
        'debug_contexts' => [
            'DebugValidator.php',
            'testing/',
            'debug/',
            'test/',
        ],
        'system_flags' => [
            'realizace_taxonomies_populated',
            'post_type_exists',
            'field_invoice_date_2025',
            'field_invoice_value_2025',
            'realizace_construction_types',
            'realizace_materials',
            'realizace_data_keys',
            'realizace_post_type_exists',
            'realizace_post_type_config',
            'realizace_stats',
            'realizace_action'
        ]
    ];

    /**
     * Scan entire codebase for field access violations
     *
     * @param string $base_path Base path to scan (defaults to src/App)
     * @return array Violation report
     */
    public static function scanCodebase(string $base_path = ''): array
    {
        if (empty($base_path)) {
            $base_path = get_stylesheet_directory() . '/src/App';
        }

        $violations = [];
        $files = self::getPhpFiles($base_path);

        foreach ($files as $file_path) {
            if (self::isFileWhitelisted($file_path)) {
                continue;
            }

            $file_violations = self::scanFile($file_path);
            if (!empty($file_violations)) {
                $violations[$file_path] = $file_violations;
            }
        }

        return [
            'total_files_scanned' => count($files),
            'files_with_violations' => count($violations),
            'violations' => $violations,
            'summary' => self::generateSummary($violations)
        ];
    }

    /**
     * Scan a single file for violations
     *
     * @param string $file_path Path to PHP file
     * @return array List of violations found
     */
    public static function scanFile(string $file_path): array
    {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return [];
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            return [];
        }

        $violations = [];
        $lines = explode("\n", $content);

        foreach (self::VIOLATION_PATTERNS as $pattern_name => $config) {
            $matches = [];
            if (preg_match_all($config['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line_number = self::getLineNumber($content, $match[1]);
                    
                    // Check if this violation should be exempted
                    if (self::isViolationExempted($file_path, $match[0], $pattern_name)) {
                        continue;
                    }
                    
                    $violations[] = [
                        'type' => $pattern_name,
                        'line' => $line_number,
                        'content' => trim($lines[$line_number - 1]),
                        'match' => $match[0],
                        'message' => $config['message'],
                        'severity' => $config['severity']
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * Validate specific domain compliance
     *
     * @param string $domain_key Domain identifier (realization, invoice)
     * @return array Domain-specific compliance report
     */
    public static function validateDomainCompliance(string $domain_key): array
    {
        $domain_paths = [
            'realization' => [
                'src/App/Realizace/',
            ],
            'invoice' => [
                'src/App/Faktury/',
            ]
        ];

        if (!isset($domain_paths[$domain_key])) {
            return ['error' => "Unknown domain: {$domain_key}"];
        }

        $base_path = get_stylesheet_directory() . '/';
        $violations = [];
        $total_files = 0;

        foreach ($domain_paths[$domain_key] as $relative_path) {
            $full_path = $base_path . $relative_path;
            if (is_dir($full_path)) {
                $files = self::getPhpFiles($full_path);
                $total_files += count($files);

                foreach ($files as $file_path) {
                    $file_violations = self::scanFile($file_path);
                    if (!empty($file_violations)) {
                        $violations[str_replace($base_path, '', $file_path)] = $file_violations;
                    }
                }
            }
        }

        return [
            'domain' => $domain_key,
            'total_files_scanned' => $total_files,
            'files_with_violations' => count($violations),
            'violations' => $violations,
            'compliance_score' => self::calculateComplianceScore($total_files, count($violations))
        ];
    }

    /**
     * Generate compliance report for CLI usage
     *
     * @param array $scan_result Result from scanCodebase()
     * @return string Human-readable report
     */
    public static function generateReport(array $scan_result): string
    {
        $report = "=== FIELD ACCESS COMPLIANCE REPORT ===\n\n";
        
        $report .= "Summary:\n";
        $report .= "- Files scanned: {$scan_result['total_files_scanned']}\n";
        $report .= "- Files with violations: {$scan_result['files_with_violations']}\n";
        
        if (isset($scan_result['summary'])) {
            foreach ($scan_result['summary'] as $severity => $count) {
                $report .= "- {$severity} violations: {$count}\n";
            }
        }
        
        $compliance = self::calculateComplianceScore(
            $scan_result['total_files_scanned'], 
            $scan_result['files_with_violations']
        );
        $report .= "- Compliance score: {$compliance}%\n\n";

        if (!empty($scan_result['violations'])) {
            $report .= "Violations by file:\n\n";
            
            foreach ($scan_result['violations'] as $file_path => $violations) {
                $report .= "📁 " . basename($file_path) . "\n";
                
                foreach ($violations as $violation) {
                    $icon = $violation['severity'] === 'error' ? '❌' : '⚠️';
                    $report .= "  {$icon} Line {$violation['line']}: {$violation['message']}\n";
                    $report .= "     → {$violation['match']}\n";
                }
                $report .= "\n";
            }
        }

        return $report;
    }

    /**
     * Get recommended fixes for violations
     *
     * @param array $violations Violations array
     * @return array Fix suggestions
     */
    public static function getFixSuggestions(array $violations): array
    {
        $suggestions = [];

        foreach ($violations as $file_path => $file_violations) {
            $file_suggestions = [];
            
            foreach ($file_violations as $violation) {
                $fix = match ($violation['type']) {
                    'hardcoded_get_field' => "Replace with FieldService::getFieldName(\$post_id)",
                    'hardcoded_update_field' => "Replace with FieldService::setFieldName(\$post_id, \$value)",
                    'hardcoded_post_meta' => "Replace with FieldService::getFieldName(\$post_id)",
                    'hardcoded_constants' => "Use DomainConfigurationService::getFieldSelector()",
                    'hardcoded_points' => "Use PointsCalculationService::calculatePoints()",
                    'hardcoded_calculation' => "Use PointsCalculationService::calculatePoints()",
                    default => "Review and refactor to use appropriate service"
                };
                
                $file_suggestions[] = [
                    'line' => $violation['line'],
                    'current' => $violation['match'],
                    'suggested_fix' => $fix,
                    'severity' => $violation['severity']
                ];
            }
            
            $suggestions[$file_path] = $file_suggestions;
        }

        return $suggestions;
    }

    /**
     * Check if current codebase meets minimum compliance standards
     *
     * @param int $minimum_score Minimum compliance score (0-100)
     * @return bool True if compliant
     */
    public static function isCompliant(int $minimum_score = 90): bool
    {
        $scan_result = self::scanCodebase();
        $compliance_score = self::calculateComplianceScore(
            $scan_result['total_files_scanned'],
            $scan_result['files_with_violations']
        );

        return $compliance_score >= $minimum_score;
    }

    /**
     * Get all PHP files recursively
     *
     * @param string $directory Directory to scan
     * @return array List of PHP file paths
     */
    private static function getPhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Check if file is whitelisted (allowed to have violations)
     *
     * @param string $file_path Path to file
     * @return bool True if whitelisted
     */
    private static function isFileWhitelisted(string $file_path): bool
    {
        foreach (self::ALLOWED_PATTERNS as $pattern) {
            if (preg_match($pattern, $file_path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get line number from string offset
     *
     * @param string $content File content
     * @param int $offset String offset
     * @return int Line number
     */
    private static function getLineNumber(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }

    /**
     * Generate violation summary by severity
     *
     * @param array $violations Violations array
     * @return array Summary counts
     */
    private static function generateSummary(array $violations): array
    {
        $summary = ['error' => 0, 'warning' => 0];

        foreach ($violations as $file_violations) {
            foreach ($file_violations as $violation) {
                $summary[$violation['severity']]++;
            }
        }

        return $summary;
    }

    /**
     * Calculate compliance score
     *
     * @param int $total_files Total files scanned
     * @param int $files_with_violations Files with violations
     * @return int Compliance score (0-100)
     */
    private static function calculateComplianceScore(int $total_files, int $files_with_violations): int
    {
        if ($total_files === 0) {
            return 100;
        }

        return (int) round((($total_files - $files_with_violations) / $total_files) * 100);
    }

    /**
     * Check if a violation should be exempted based on context
     *
     * @param string $file_path File path being scanned
     * @param string $match The matched violation string
     * @param string $pattern_name The pattern type that matched
     * @return bool True if violation should be exempted
     */
    private static function isViolationExempted(string $file_path, string $match, string $pattern_name): bool
    {
        // Check debug contexts
        foreach (self::CONTEXT_EXEMPTIONS['debug_contexts'] as $debug_context) {
            if (strpos($file_path, $debug_context) !== false) {
                return true;
            }
        }

        // Check system flags for hardcoded constants
        if ($pattern_name === 'hardcoded_constants') {
            foreach (self::CONTEXT_EXEMPTIONS['system_flags'] as $system_flag) {
                if (strpos($match, $system_flag) !== false) {
                    return true;
                }
            }
            
            // Check if this is a DomainConfigurationService parameter
            if (self::isDomainConfigurationServiceParameter($file_path, $match)) {
                return true;
            }
        }

        // Check if file is whitelisted
        if (self::isFileWhitelisted($file_path)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a matched string is a parameter to DomainConfigurationService methods
     *
     * @param string $file_path File path being scanned
     * @param string $match The matched string
     * @return bool True if this is a DomainConfigurationService parameter
     */
    private static function isDomainConfigurationServiceParameter(string $file_path, string $match): bool
    {
        // Read the file content around the match
        $content = file_get_contents($file_path);
        if ($content === false) {
            return false;
        }

        // Look for patterns where the match is used as a parameter to DomainConfigurationService methods
        $patterns = [
            '/DomainConfigurationService::getFieldName\([^,]+,\s*' . preg_quote($match, '/') . '\)/',
            '/DomainConfigurationService::getFieldSelector\([^,]+,\s*' . preg_quote($match, '/') . '\)/',
            '/DomainConfigurationService::getConfigValue\([^,]+,\s*' . preg_quote($match, '/') . '\)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}