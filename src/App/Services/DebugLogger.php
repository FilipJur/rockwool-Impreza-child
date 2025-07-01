<?php
namespace MistrFachman\Services;

class DebugLogger {
    public static function log(string $message, array $context = []) {
        // Use child theme directory for log file
        $log_path = get_stylesheet_directory() . '/debug_faktury.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message";

        if (!empty($context)) {
            // Use var_export for a more readable array output
            $log_entry .= "\n" . print_r($context, true);
        }

        file_put_contents($log_path, $log_entry . "\n\n", FILE_APPEND);
    }
}
