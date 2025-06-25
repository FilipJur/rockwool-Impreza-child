<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Domain Registry Validator - Test and Validation Utility
 *
 * Provides testing and validation methods for the DomainRegistry service
 * to ensure consistency and catch configuration errors.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DomainRegistryValidator {

    /**
     * Test all domain configurations for consistency
     *
     * @return array Test results
     */
    public static function runAllTests(): array {
        $results = [
            'tests_passed' => 0,
            'tests_failed' => 0,
            'domains_tested' => [],
            'errors' => [],
            'warnings' => []
        ];

        $domains = DomainRegistry::getAllDomains();

        foreach ($domains as $domain_key => $config) {
            $test_result = self::testDomain($domain_key);
            $results['domains_tested'][] = $domain_key;

            if ($test_result['valid']) {
                $results['tests_passed']++;
            } else {
                $results['tests_failed']++;
                $results['errors'][] = "Domain '{$domain_key}': " . implode(', ', $test_result['errors']);
            }

            if (!empty($test_result['warnings'])) {
                $results['warnings'][] = "Domain '{$domain_key}': " . implode(', ', $test_result['warnings']);
            }
        }

        return $results;
    }

    /**
     * Test a specific domain configuration
     *
     * @param string $domain Domain key to test
     * @return array Test results
     */
    public static function testDomain(string $domain): array {
        $errors = [];
        $warnings = [];

        try {
            // Test basic registry access
            $post_type = DomainRegistry::getPostType($domain);
            $script_prefix = DomainRegistry::getScriptPrefix($domain);
            $js_slug = DomainRegistry::getJsSlug($domain);
            $ajax_prefix = DomainRegistry::getAjaxPrefix($domain);

            // Test AJAX action generation
            $quick_action = DomainRegistry::getAjaxAction($domain, 'quick_action');
            $bulk_action = DomainRegistry::getAjaxAction($domain, 'bulk_approve');

            // Test nonce action generation
            $quick_nonce = DomainRegistry::getNonceAction($domain, 'quick_action');
            $bulk_nonce = DomainRegistry::getNonceAction($domain, 'bulk_approve');

            // Validate consistency rules
            if ($ajax_prefix !== $post_type) {
                $errors[] = "AJAX prefix '{$ajax_prefix}' should match post type '{$post_type}'";
            }

            // Check AJAX action format
            $expected_quick = "mistr_fachman_{$post_type}_quick_action";
            if ($quick_action !== $expected_quick) {
                $errors[] = "Quick action mismatch: got '{$quick_action}', expected '{$expected_quick}'";
            }

            $expected_bulk = "mistr_fachman_bulk_approve_{$post_type}";
            if ($bulk_action !== $expected_bulk) {
                $errors[] = "Bulk action mismatch: got '{$bulk_action}', expected '{$expected_bulk}'";
            }

            // Check nonce format
            $expected_quick_nonce = "mistr_fachman_{$post_type}_action";
            if ($quick_nonce !== $expected_quick_nonce) {
                $errors[] = "Quick nonce mismatch: got '{$quick_nonce}', expected '{$expected_quick_nonce}'";
            }

            // Run built-in validation
            $validation = DomainRegistry::validateDomain($domain);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['warnings']);
            } else {
                $warnings = array_merge($warnings, $validation['warnings']);
            }

        } catch (\Exception $e) {
            $errors[] = "Exception during testing: " . $e->getMessage();
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Test that JavaScript endpoints would match PHP expectations
     *
     * @param string $domain Domain key
     * @return array Test results for JS/PHP consistency
     */
    public static function testJavaScriptConsistency(string $domain): array {
        $results = [
            'consistent' => true,
            'mismatches' => []
        ];

        try {
            // Expected PHP AJAX hooks
            $php_quick_action = DomainRegistry::getAjaxAction($domain, 'quick_action');
            $php_bulk_action = DomainRegistry::getAjaxAction($domain, 'bulk_approve');

            // What JavaScript should be calling (based on current patterns)
            $post_type = DomainRegistry::getPostType($domain);
            $js_quick_expected = "mistr_fachman_{$post_type}_quick_action";
            $js_bulk_expected = "mistr_fachman_bulk_approve_{$post_type}";

            if ($php_quick_action !== $js_quick_expected) {
                $results['consistent'] = false;
                $results['mismatches'][] = "Quick action: PHP expects '{$php_quick_action}', JS should call '{$js_quick_expected}'";
            }

            if ($php_bulk_action !== $js_bulk_expected) {
                $results['consistent'] = false;
                $results['mismatches'][] = "Bulk action: PHP expects '{$php_bulk_action}', JS should call '{$js_bulk_expected}'";
            }

        } catch (\Exception $e) {
            $results['consistent'] = false;
            $results['mismatches'][] = "Exception: " . $e->getMessage();
        }

        return $results;
    }

    /**
     * Generate validation report
     *
     * @return string Human-readable validation report
     */
    public static function generateReport(): string {
        $results = self::runAllTests();
        
        $report = "=== Domain Registry Validation Report ===\n\n";
        $report .= "Tests passed: {$results['tests_passed']}\n";
        $report .= "Tests failed: {$results['tests_failed']}\n";
        $report .= "Domains tested: " . implode(', ', $results['domains_tested']) . "\n\n";

        if (!empty($results['errors'])) {
            $report .= "ERRORS:\n";
            foreach ($results['errors'] as $error) {
                $report .= "- {$error}\n";
            }
            $report .= "\n";
        }

        if (!empty($results['warnings'])) {
            $report .= "WARNINGS:\n";
            foreach ($results['warnings'] as $warning) {
                $report .= "- {$warning}\n";
            }
            $report .= "\n";
        }

        // Test JavaScript consistency for each domain
        $report .= "=== JavaScript Consistency ===\n";
        foreach ($results['domains_tested'] as $domain) {
            $js_test = self::testJavaScriptConsistency($domain);
            $status = $js_test['consistent'] ? 'PASS' : 'FAIL';
            $report .= "{$domain}: {$status}\n";
            
            if (!$js_test['consistent']) {
                foreach ($js_test['mismatches'] as $mismatch) {
                    $report .= "  - {$mismatch}\n";
                }
            }
        }

        if ($results['tests_failed'] === 0) {
            $report .= "\n✅ All domain configurations are valid!\n";
        } else {
            $report .= "\n❌ Some domain configurations have issues that need to be fixed.\n";
        }

        return $report;
    }
}