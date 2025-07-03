<?php
namespace MistrFachman\Services;

class DebugLogger {
    
    /**
     * Log to default debug_realizace.log file
     */
    public static function log(string $message, array $context = []) {
        self::logToFile('realizace', $message, $context);
    }

    /**
     * Log to specific log file with custom prefix
     * 
     * @param string $log_prefix Prefix for log file name (e.g., 'dual_points' -> 'debug_dual_points.log')
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function logToFile(string $log_prefix, string $message, array $context = []) {
        // Use child theme directory for log file
        $log_path = get_stylesheet_directory() . "/debug_{$log_prefix}.log";
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message";

        if (!empty($context)) {
            // Use var_export for a more readable array output
            $log_entry .= "\n" . print_r($context, true);
        }

        file_put_contents($log_path, $log_entry . "\n\n", FILE_APPEND);
    }
}
