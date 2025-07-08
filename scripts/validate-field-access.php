#!/usr/bin/env php
<?php

/**
 * Field Access Validation Script
 *
 * Validates codebase compliance with FieldService patterns.
 * Usage: php scripts/validate-field-access.php [domain]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Set up WordPress environment
$wp_root = dirname(__DIR__, 4);
require_once $wp_root . '/wp-config.php';

// Load autoloader
$autoloader_path = __DIR__ . '/../lib/autoload.php';
if (file_exists($autoloader_path)) {
    require_once $autoloader_path;
} else {
    echo "❌ Autoloader not found. Run: composer install\n";
    exit(1);
}

use MistrFachman\Services\FieldAccessValidator;
use MistrFachman\Services\DomainConfigurationService;

// Initialize services
DomainConfigurationService::initialize();

// Get command line arguments
$domain = $argv[1] ?? null;

echo "🔍 Field Access Compliance Validator\n";
echo "=====================================\n\n";

if ($domain) {
    echo "Scanning domain: {$domain}\n\n";
    $result = FieldAccessValidator::validateDomainCompliance($domain);
    
    if (isset($result['error'])) {
        echo "❌ Error: {$result['error']}\n";
        exit(1);
    }
    
    echo "Domain: {$result['domain']}\n";
    echo "Files scanned: {$result['total_files_scanned']}\n";
    echo "Files with violations: {$result['files_with_violations']}\n";
    echo "Compliance score: {$result['compliance_score']}%\n\n";
    
    if (!empty($result['violations'])) {
        echo "Violations:\n";
        foreach ($result['violations'] as $file_path => $violations) {
            echo "📁 {$file_path}\n";
            foreach ($violations as $violation) {
                $icon = $violation['severity'] === 'error' ? '❌' : '⚠️';
                echo "  {$icon} Line {$violation['line']}: {$violation['message']}\n";
            }
            echo "\n";
        }
    } else {
        echo "✅ No violations found!\n";
    }
} else {
    echo "Scanning entire codebase...\n\n";
    $result = FieldAccessValidator::scanCodebase();
    $report = FieldAccessValidator::generateReport($result);
    echo $report;
    
    // Exit with error code if violations found
    if ($result['files_with_violations'] > 0) {
        echo "\n💡 Run with domain name to focus scan: php validate-field-access.php realization\n";
        exit(1);
    }
}

echo "✅ Validation complete!\n";
exit(0);