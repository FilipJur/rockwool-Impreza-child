<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Error Handling Service
 *
 * Unified error handling and logging patterns across all domains.
 * Provides consistent error reporting, logging, and exception handling.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ErrorHandlingService
{
    /**
     * Error severity levels
     */
    public const SEVERITY_DEBUG = 'debug';
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Error categories
     */
    public const CATEGORY_VALIDATION = 'validation';
    public const CATEGORY_CALCULATION = 'calculation';
    public const CATEGORY_FIELD_ACCESS = 'field_access';
    public const CATEGORY_AJAX = 'ajax';
    public const CATEGORY_AUTHENTICATION = 'authentication';
    public const CATEGORY_CONFIGURATION = 'configuration';
    public const CATEGORY_DOMAIN = 'domain';

    /**
     * Log error with structured format
     *
     * @param string $message Error message
     * @param string $category Error category constant
     * @param string $severity Severity level constant
     * @param array $context Additional context data
     * @param \Throwable|null $exception Optional exception object
     */
    public static function logError(
        string $message,
        string $category = self::CATEGORY_DOMAIN,
        string $severity = self::SEVERITY_ERROR,
        array $context = [],
        ?\Throwable $exception = null
    ): void {
        // Only log if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        // Format the log entry
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'severity' => $severity,
            'category' => $category,
            'message' => $message,
        ];

        // Add context if provided
        if (!empty($context)) {
            $log_entry['context'] = $context;
        }

        // Add exception details if provided
        if ($exception) {
            $log_entry['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        // Format for WordPress error log
        $formatted_message = sprintf(
            '[MISTR-FACHMAN:%s:%s] %s',
            strtoupper($category),
            strtoupper($severity),
            $message
        );

        if (!empty($context) || $exception) {
            $formatted_message .= ' | Data: ' . wp_json_encode($log_entry);
        }

        error_log($formatted_message);

        // Log critical errors to a separate file if possible
        if ($severity === self::SEVERITY_CRITICAL) {
            self::logCriticalError($log_entry);
        }
    }

    /**
     * Handle validation errors with user-friendly messages
     *
     * @param array $validation_result Result from ValidationRulesRegistry
     * @param string $context Validation context
     * @return array Formatted error response
     */
    public static function handleValidationError(array $validation_result, string $context = 'general'): array
    {
        if ($validation_result['success']) {
            return $validation_result;
        }

        $error_message = $validation_result['message'] ?? 'Validation failed';
        
        self::logError(
            "Validation failed in context: {$context}",
            self::CATEGORY_VALIDATION,
            self::SEVERITY_WARNING,
            [
                'context' => $context,
                'validation_result' => $validation_result
            ]
        );

        return [
            'success' => false,
            'message' => $error_message,
            'error_code' => 'VALIDATION_FAILED',
            'context' => $context
        ];
    }

    /**
     * Handle AJAX errors with proper response format
     *
     * @param string $message Error message
     * @param array $context Additional context
     * @param \Throwable|null $exception Optional exception
     */
    public static function handleAjaxError(
        string $message,
        array $context = [],
        ?\Throwable $exception = null
    ): void {
        self::logError(
            "AJAX error: {$message}",
            self::CATEGORY_AJAX,
            self::SEVERITY_ERROR,
            $context,
            $exception
        );

        wp_send_json_error([
            'message' => $message,
            'error_code' => 'AJAX_ERROR',
            'timestamp' => current_time('timestamp')
        ]);
    }

    /**
     * Handle calculation errors
     *
     * @param string $domain_key Domain identifier
     * @param int $post_id Post ID
     * @param string $message Error message
     * @param \Throwable|null $exception Optional exception
     * @return int Safe fallback value (0)
     */
    public static function handleCalculationError(
        string $domain_key,
        int $post_id,
        string $message,
        ?\Throwable $exception = null
    ): int {
        self::logError(
            "Calculation error for {$domain_key} post {$post_id}: {$message}",
            self::CATEGORY_CALCULATION,
            self::SEVERITY_ERROR,
            [
                'domain' => $domain_key,
                'post_id' => $post_id
            ],
            $exception
        );

        // Return safe fallback
        return 0;
    }

    /**
     * Handle field access errors
     *
     * @param string $domain_key Domain identifier
     * @param string $field_key Field identifier
     * @param int $post_id Post ID
     * @param string $operation Operation (get/set)
     * @param \Throwable|null $exception Optional exception
     * @return mixed Safe fallback value
     */
    public static function handleFieldAccessError(
        string $domain_key,
        string $field_key,
        int $post_id,
        string $operation,
        ?\Throwable $exception = null
    ) {
        self::logError(
            "Field access error: {$operation} {$domain_key}.{$field_key} for post {$post_id}",
            self::CATEGORY_FIELD_ACCESS,
            self::SEVERITY_ERROR,
            [
                'domain' => $domain_key,
                'field' => $field_key,
                'post_id' => $post_id,
                'operation' => $operation
            ],
            $exception
        );

        // Return safe fallbacks based on operation
        return match ($operation) {
            'get' => self::getSafeFallbackValue($field_key),
            'set' => false,
            default => null
        };
    }

    /**
     * Handle configuration errors
     *
     * @param string $config_key Configuration key that failed
     * @param string $message Error message
     * @param \Throwable|null $exception Optional exception
     */
    public static function handleConfigurationError(
        string $config_key,
        string $message,
        ?\Throwable $exception = null
    ): void {
        self::logError(
            "Configuration error for key '{$config_key}': {$message}",
            self::CATEGORY_CONFIGURATION,
            self::SEVERITY_CRITICAL,
            ['config_key' => $config_key],
            $exception
        );
    }

    /**
     * Create standardized exception with context
     *
     * @param string $message Exception message
     * @param string $category Error category
     * @param array $context Additional context
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     * @return \Exception Standardized exception
     */
    public static function createException(
        string $message,
        string $category = self::CATEGORY_DOMAIN,
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ): \Exception {
        // Log the exception creation
        self::logError(
            "Exception created: {$message}",
            $category,
            self::SEVERITY_ERROR,
            $context
        );

        $formatted_message = empty($context) 
            ? $message 
            : $message . ' | Context: ' . wp_json_encode($context);

        return new \Exception($formatted_message, $code, $previous);
    }

    /**
     * Get error statistics for monitoring
     *
     * @param string $time_period Time period (day, week, month)
     * @return array Error statistics
     */
    public static function getErrorStatistics(string $time_period = 'day'): array
    {
        // This would integrate with a proper logging system in production
        // For now, return basic structure
        return [
            'period' => $time_period,
            'total_errors' => 0,
            'by_severity' => [
                self::SEVERITY_CRITICAL => 0,
                self::SEVERITY_ERROR => 0,
                self::SEVERITY_WARNING => 0,
                self::SEVERITY_INFO => 0,
                self::SEVERITY_DEBUG => 0
            ],
            'by_category' => [
                self::CATEGORY_VALIDATION => 0,
                self::CATEGORY_CALCULATION => 0,
                self::CATEGORY_FIELD_ACCESS => 0,
                self::CATEGORY_AJAX => 0,
                self::CATEGORY_AUTHENTICATION => 0,
                self::CATEGORY_CONFIGURATION => 0,
                self::CATEGORY_DOMAIN => 0
            ],
            'last_updated' => current_time('mysql')
        ];
    }

    /**
     * Check if error logging is properly configured
     *
     * @return array Configuration status
     */
    public static function validateErrorLoggingConfiguration(): array
    {
        $issues = [];

        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            $issues[] = 'WP_DEBUG is not enabled';
        }

        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            $issues[] = 'WP_DEBUG_LOG is not enabled';
        }

        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (!is_writable(dirname($log_file))) {
            $issues[] = 'Debug log directory is not writable';
        }

        return [
            'is_configured' => empty($issues),
            'issues' => $issues,
            'log_file' => $log_file,
            'recommendations' => empty($issues) ? [] : [
                'Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php',
                'Ensure wp-content directory is writable',
                'Consider implementing structured logging for production'
            ]
        ];
    }

    /**
     * Log critical errors to separate file
     *
     * @param array $log_entry Formatted log entry
     */
    private static function logCriticalError(array $log_entry): void
    {
        $critical_log_file = WP_CONTENT_DIR . '/mistr-fachman-critical.log';
        
        $formatted_entry = sprintf(
            "[%s] CRITICAL: %s\n%s\n\n",
            $log_entry['timestamp'],
            $log_entry['message'],
            wp_json_encode($log_entry, JSON_PRETTY_PRINT)
        );

        // Attempt to write to critical log file
        @file_put_contents($critical_log_file, $formatted_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get safe fallback value for field operations
     *
     * @param string $field_key Field identifier
     * @return mixed Safe fallback value
     */
    private static function getSafeFallbackValue(string $field_key)
    {
        return match (true) {
            str_contains($field_key, 'points') => 0,
            str_contains($field_key, 'value') => 0,
            str_contains($field_key, 'reason') => '',
            str_contains($field_key, 'date') => '',
            str_contains($field_key, 'number') => '',
            str_contains($field_key, 'gallery') => [],
            str_contains($field_key, 'file') => null,
            default => ''
        };
    }
}