<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;
use MistrFachman\Services\ZebricekDataService;

/**
 * Žebříček (Leaderboard) Shortcode Component
 *
 * Dynamic leaderboard displaying top users ranked by lifetime points earned.
 * Shows different content based on user access level:
 * - Pending users: Names only (points hidden)
 * - Full members: Names, companies, and points
 *
 * Usage: [zebricek_leaderboard limit="30" offset="0"]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZebricekLeaderboardShortcode extends ShortcodeBase
{
    protected array $default_attributes = [
        'limit' => '30',
        'offset' => '0',
        'show_numbers' => 'true ',
        'show_pagination' => 'true',
        'class' => ''
    ];

    private const CACHE_KEY_PREFIX = 'zebricek_leaderboard_';
    private const CACHE_DURATION = 900; // 15 minutes

    private ZebricekDataService $zebricek_service;

    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service, ZebricekDataService $zebricek_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
        $this->zebricek_service = $zebricek_service;
    }

    public function get_tag(): string
    {
        return 'zebricek_leaderboard';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // $content parameter unused for this shortcode type
        $user_id = get_current_user_id();
        $status = $this->user_service->get_user_registration_status($user_id);

        // Determine access level
        $is_full_member = $status === 'full_member' || $status === 'other';

        // Get leaderboard data via service
        $current_year = date('Y');
        $leaderboard_data = $this->zebricek_service->get_leaderboard_data(
            (int)$attributes['limit'],
            (int)$attributes['offset'],
            $current_year
        );

        // Prepare template data
        $template_data = [
            'leaderboard_data' => $leaderboard_data,
            'attributes' => $attributes,
            'is_full_member' => $is_full_member,
            'wrapper_classes' => $this->get_wrapper_classes($attributes),
            'renderer' => $this
        ];

        return $this->load_template('zebricek/leaderboard.php', $template_data);
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
        $log_entry = "[$timestamp] $message";

        if ($data !== null) {
            $log_entry .= "\n" . print_r($data, true);
        }

        file_put_contents($log_path, $log_entry . "\n\n", FILE_APPEND);
    }



    /**
     * Clear cache when needed (delegates to service)
     */
    public static function clear_cache(): void
    {
        ZebricekDataService::clear_cache();
    }

    /**
     * Override sanitize_attributes with enhanced validation
     */
    protected function sanitize_attributes(array $attributes): array
    {
        $sanitized = parent::sanitize_attributes($attributes);

        // Enhanced validation for leaderboard-specific attributes
        $sanitized['limit'] = max(1, min(100, absint($sanitized['limit']))); // 1-100 range
        $sanitized['offset'] = max(0, absint($sanitized['offset']));
        $sanitized['show_numbers'] = in_array($sanitized['show_numbers'], ['true', 'false'], true) ? $sanitized['show_numbers'] : 'true';
        $sanitized['show_pagination'] = in_array($sanitized['show_pagination'], ['true', 'false'], true) ? $sanitized['show_pagination'] : 'true';

        return $sanitized;
    }
}

// Clear leaderboard cache when posts are approved/disapproved
add_action('transition_post_status', function($new_status, $old_status, $post) {
    // Only clear cache for realizace and faktury posts (using WordPress post type names)
    if (in_array($post->post_type, ['realization', 'invoice'])) {
        ZebricekLeaderboardShortcode::clear_cache();

        // Also clear position cache for affected user (post author for these post types)
        $user_id = (int)$post->post_author;
        if ($user_id > 0 && class_exists('MistrFachman\\Shortcodes\\ZebricekPositionShortcode')) {
            ZebricekPositionShortcode::clear_user_cache($user_id);
        }
    }
}, 10, 3);

// Note: We only use post status transition hook for cache clearing
// to avoid interfering with WooCommerce/myCred payment processing
