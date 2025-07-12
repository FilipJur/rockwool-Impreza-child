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

    /**
     * Dedicated points flow logging for tracking calculations and assignments
     * Creates debug_points_flow.log with structured logging
     * 
     * @param string $operation Operation type (calculation, assignment, recalculation, etc.)
     * @param string $domain Domain (invoice, realization)
     * @param int $post_id Post ID
     * @param array $data Operation-specific data
     */
    public static function logPointsFlow(string $operation, string $domain, int $post_id, array $data = []) {
        // Only log if WP_DEBUG is enabled to avoid spam
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s.u'); // Include microseconds for precise timing
        $log_path = get_stylesheet_directory() . "/debug_points_flow.log";
        
        // Get current user context
        $current_user_id = get_current_user_id();
        $current_user = $current_user_id ? get_userdata($current_user_id) : null;
        
        // Get post context
        $post = get_post($post_id);
        $post_status = $post ? $post->post_status : 'unknown';
        $post_author = $post ? $post->post_author : 'unknown';
        
        // Create structured log entry
        $log_data = [
            'timestamp' => $timestamp,
            'operation' => $operation,
            'domain' => $domain,
            'post_context' => [
                'post_id' => $post_id,
                'post_status' => $post_status,
                'post_author' => $post_author
            ],
            'user_context' => [
                'current_user_id' => $current_user_id,
                'current_user_login' => $current_user ? $current_user->user_login : 'guest',
                'is_admin' => current_user_can('manage_options')
            ],
            'request_context' => [
                'is_ajax' => wp_doing_ajax(),
                'is_admin_area' => is_admin(),
                'hook_context' => self::getCurrentHookContext()
            ],
            'operation_data' => $data
        ];

        // Format as readable log entry
        $log_entry = sprintf(
            "[%s] %s/%s - Post #%d (%s)",
            $timestamp,
            strtoupper($domain),
            strtoupper($operation),
            $post_id,
            $post_status
        );

        // Add key data points on same line for quick scanning
        if (isset($data['points_calculated'])) {
            $log_entry .= sprintf(" | Calculated: %d pts", $data['points_calculated']);
        }
        if (isset($data['points_awarded'])) {
            $log_entry .= sprintf(" | Awarded: %d pts", $data['points_awarded']);
        }
        if (isset($data['invoice_value'])) {
            $log_entry .= sprintf(" | Value: %d CZK", $data['invoice_value']);
        }
        if (isset($data['success'])) {
            $log_entry .= sprintf(" | Success: %s", $data['success'] ? 'YES' : 'NO');
        }
        if (isset($data['duplicate_prevention'])) {
            $log_entry .= sprintf(" | Duplicate: %s", $data['duplicate_prevention'] ? 'PREVENTED' : 'NEW');
        }

        $log_entry .= "\n";

        // Add detailed context if significant data present
        if (!empty($data) && (
            isset($data['error']) || 
            isset($data['validation_failed']) || 
            isset($data['calculation_details']) ||
            isset($data['hook_trace'])
        )) {
            $log_entry .= "  Details: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }

        $log_entry .= "\n";

        file_put_contents($log_path, $log_entry, FILE_APPEND);
    }

    /**
     * Log calculation-specific events
     */
    public static function logCalculation(string $domain, int $post_id, array $data = []) {
        self::logPointsFlow('calculation', $domain, $post_id, $data);
    }

    /**
     * Log assignment/awarding events
     */
    public static function logAssignment(string $domain, int $post_id, array $data = []) {
        self::logPointsFlow('assignment', $domain, $post_id, $data);
    }

    /**
     * Log recalculation events
     */
    public static function logRecalculation(string $domain, int $post_id, array $data = []) {
        self::logPointsFlow('recalculation', $domain, $post_id, $data);
    }

    /**
     * Log validation events
     */
    public static function logValidation(string $domain, int $post_id, array $data = []) {
        self::logPointsFlow('validation', $domain, $post_id, $data);
    }

    /**
     * Log field update events
     */
    public static function logFieldUpdate(string $domain, int $post_id, array $data = []) {
        self::logPointsFlow('field_update', $domain, $post_id, $data);
    }

    /**
     * Get current WordPress hook context for debugging
     */
    private static function getCurrentHookContext(): array {
        global $wp_current_filter;
        
        return [
            'current_filter' => $wp_current_filter ?? [],
            'doing_action' => doing_action() ?: 'none',
            'current_action' => current_action() ?: 'none'
        ];
    }

    /**
     * Clear points flow log (useful for focused debugging sessions)
     */
    public static function clearPointsLog(): bool {
        $log_path = get_stylesheet_directory() . "/debug_points_flow.log";
        if (file_exists($log_path)) {
            return unlink($log_path);
        }
        return true;
    }

    /**
     * Get last N lines from points flow log (for debugging)
     */
    public static function getRecentPointsLog(int $lines = 50): array {
        $log_path = get_stylesheet_directory() . "/debug_points_flow.log";
        if (!file_exists($log_path)) {
            return [];
        }

        $content = file_get_contents($log_path);
        $log_lines = explode("\n", $content);
        return array_slice($log_lines, -$lines);
    }
}
