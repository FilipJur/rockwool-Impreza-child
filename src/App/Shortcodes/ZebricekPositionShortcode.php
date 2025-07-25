<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;
use MistrFachman\Services\ZebricekDataService;
use MistrFachman\Services\ProjectStatusService;
use MistrFachman\Services\DomainConfigurationService;

/**
 * Žebříček Position Shortcode Component
 *
 * Displays current user's position in the leaderboard and their annual points.
 * Shows different content based on user access level and login status.
 *
 * Usage: [zebricek_position]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZebricekPositionShortcode extends ShortcodeBase
{
    protected array $default_attributes = [
        'show_annual_points' => 'true',
        'current_year' => '',
        'class' => ''
    ];

    private const CACHE_KEY_PREFIX = 'zebricek_position_';
    private const CACHE_DURATION = 600; // 10 minutes

    private ZebricekDataService $zebricek_service;

    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service, ZebricekDataService $zebricek_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
        $this->zebricek_service = $zebricek_service;
    }

    public function get_tag(): string
    {
        return 'zebricek_position';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // $content parameter unused for this shortcode type
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            $user_status = 'logged_out';
            $position_data = null;
        } else {
            $status = $this->user_service->get_user_registration_status($user_id);
            
            if ($status !== 'full_member' && $status !== 'other') {
                $user_status = 'pending';
                $position_data = null;
            } else {
                $user_status = 'full_member';
                $current_year = !empty($attributes['current_year']) ? $attributes['current_year'] : date('Y');
                $position_data = $this->get_position_data_via_service($user_id, $current_year);
            }
        }

        // Prepare template data
        $template_data = [
            'position_data' => $position_data,
            'attributes' => $attributes,
            'wrapper_classes' => $this->get_wrapper_classes($attributes),
            'user_status' => $user_status,
            'renderer' => $this
        ];

        return $this->load_template('zebricek/position.php', $template_data);
    }

    /**
     * Get position data via service
     * Now fully delegates to centralized service for all data
     */
    private function get_position_data_via_service(int $user_id, string $year): array
    {
        // Get user's position and annual points via service
        $position = $this->zebricek_service->get_user_position($user_id, $year);
        $annual_points = $this->zebricek_service->get_user_annual_points($user_id, $year);
        
        // Get count of successful submissions for current year via service
        $submission_counts = $this->zebricek_service->get_user_submission_counts($user_id, $year);
        
        return [
            'position' => $position,
            'position_formatted' => $position, // Just the number for Figma design
            'annual_points' => $annual_points,
            'annual_points_formatted' => number_format($annual_points, 0, ',', ' ') . ' b.',
            'realizations_count' => $submission_counts['realizations_count'],
            'invoices_count' => $submission_counts['invoices_count'],
            'year' => $year
        ];
    }

    // REMOVED: get_user_realizations_count() and get_user_invoices_count()
    // These methods have been eliminated in favor of centralized service approach
    // All submission counting now handled by ZebricekDataService::get_user_submission_counts()

    /**
     * Clear position cache for specific user (delegates to service)
     */
    public static function clear_user_cache(int $user_id): void
    {
        // All cache clearing now handled by centralized service
        ZebricekDataService::clear_user_cache($user_id);
        
        // No need to clear individual submission counts - handled by service
    }

    /**
     * Override sanitize_attributes with enhanced validation
     */
    protected function sanitize_attributes(array $attributes): array
    {
        $sanitized = parent::sanitize_attributes($attributes);
        
        // Sanitize position-specific attributes
        $sanitized['show_annual_points'] = in_array($sanitized['show_annual_points'], ['true', 'false'], true) ? $sanitized['show_annual_points'] : 'true';
        $sanitized['current_year'] = !empty($sanitized['current_year']) ? sanitize_text_field($sanitized['current_year']) : date('Y');
        
        return $sanitized;
    }

    /**
     * Debug logging specifically for zebricek
     */
    private function debug_log(string $message, $data = null): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_path = get_stylesheet_directory() . '/debug_zebricek.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] POSITION: $message";
        
        if ($data !== null) {
            $log_entry .= "\n" . print_r($data, true);
        }
        
        file_put_contents($log_path, $log_entry . "\n\n", FILE_APPEND);
    }
}