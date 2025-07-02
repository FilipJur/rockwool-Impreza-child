<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * Žebříček (Leaderboard) Shortcode Component V2
 * 
 * Uses native myCred shortcodes with timeframe support
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZebricekLeaderboardShortcodeV2 extends ShortcodeBase
{
    protected array $default_attributes = [
        'limit' => '30',
        'offset' => '0',
        'show_numbers' => 'true',
        'show_pagination' => 'true',
        'year' => '', // Empty means current year
        'class' => ''
    ];

    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
    }

    public function get_tag(): string
    {
        return 'zebricek_leaderboard_v2';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        $user_id = get_current_user_id();
        $status = $this->user_service->get_user_registration_status($user_id);
        $is_full_member = $status === 'full_member' || $status === 'other';
        
        // Determine year
        $year = !empty($attributes['year']) ? $attributes['year'] : date('Y');
        
        // Build timeframe for myCred shortcode
        $timeframe = $year . '-01-01,00:00:00';
        
        // Build myCred shortcode parameters
        $mycred_params = [
            'number' => $attributes['limit'],
            'offset' => $attributes['offset'],
            'order' => 'DESC',
            'timeframe' => $timeframe,
            'based_on' => 'total', // This might need to be 'balance' depending on myCred version
            'total' => 0, // Show current year total, not lifetime
            'exclude_zero' => 1
        ];
        
        // Create custom template based on user access
        if ($is_full_member) {
            // Full members see everything
            $template = '<li class="zebricek-row" data-user-id="%user_id%">
                <span class="position">%position%.</span>
                <span class="name">%display_name%</span>
                <span class="company">%company%</span>
                <span class="points">%cred_f%</span>
                <a href="%profile_url%" class="view-realizations">Zobrazit realizace</a>
            </li>';
        } else {
            // Pending users see names only
            $template = '<li class="zebricek-row zebricek-row--restricted" data-user-id="%user_id%">
                <span class="position">%position%.</span>
                <span class="name">%display_name%</span>
                <span class="restricted-message">Pro zobrazení bodů se přihlaste</span>
            </li>';
        }
        
        // Add template to parameters
        $mycred_params['template'] = $template;
        
        // Build shortcode string
        $shortcode_str = '[mycred_leaderboard';
        foreach ($mycred_params as $key => $value) {
            if ($key === 'template') {
                $shortcode_str .= ' ' . $key . '="' . esc_attr($value) . '"';
            } else {
                $shortcode_str .= ' ' . $key . '="' . $value . '"';
            }
        }
        $shortcode_str .= ']';
        
        $this->debug_log('Using native myCred shortcode', [
            'year' => $year,
            'timeframe' => $timeframe,
            'shortcode' => $shortcode_str
        ]);
        
        // Execute the shortcode
        $leaderboard_output = do_shortcode($shortcode_str);
        
        // Check if we got valid output
        if (empty($leaderboard_output) || strpos($leaderboard_output, 'mycred-leaderboard') === false) {
            $this->debug_log('No output from myCred shortcode, trying alternative approach');
            
            // Try alternative: use based_on="total" with current year timeframe
            $mycred_params['based_on'] = 'total';
            $shortcode_str = $this->build_shortcode_string($mycred_params);
            $leaderboard_output = do_shortcode($shortcode_str);
        }
        
        // If still no output, try without timeframe (for debugging)
        if (empty($leaderboard_output)) {
            $this->debug_log('Still no output, trying without timeframe');
            unset($mycred_params['timeframe']);
            $shortcode_str = $this->build_shortcode_string($mycred_params);
            $leaderboard_output = do_shortcode($shortcode_str);
        }
        
        // Wrap the output
        $wrapper_classes = $this->get_wrapper_classes($attributes);
        
        $output = '<div class="' . esc_attr($wrapper_classes) . ' zebricek-wrapper">';
        
        if ($attributes['show_numbers'] === 'true') {
            $output .= '<div class="zebricek-stats">';
            $output .= '<p>Žebříček pro rok ' . esc_html($year) . '</p>';
            $output .= '</div>';
        }
        
        if (!empty($leaderboard_output) && strpos($leaderboard_output, '<li') !== false) {
            $output .= $leaderboard_output;
        } else {
            $output .= '<div class="zebricek-empty"><p>Žebříček zatím neobsahuje žádné uživatele.</p></div>';
        }
        
        if ($attributes['show_pagination'] === 'true') {
            $output .= $this->render_pagination($attributes);
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Build shortcode string from parameters
     */
    private function build_shortcode_string(array $params): string
    {
        $shortcode_str = '[mycred_leaderboard';
        foreach ($params as $key => $value) {
            if ($key === 'template') {
                $shortcode_str .= ' ' . $key . '="' . esc_attr($value) . '"';
            } else {
                $shortcode_str .= ' ' . $key . '="' . $value . '"';
            }
        }
        $shortcode_str .= ']';
        return $shortcode_str;
    }
    
    /**
     * Render pagination
     */
    private function render_pagination(array $attributes): string
    {
        $offset = (int)$attributes['offset'];
        $limit = (int)$attributes['limit'];
        $next_offset = $offset + $limit;
        
        return sprintf(
            '<div class="zebricek-pagination" data-offset="%d" data-limit="%d">
                <button class="zebricek-load-more">Více</button>
            </div>',
            $next_offset,
            $limit
        );
    }
    
    /**
     * Debug logging
     */
    private function debug_log(string $message, $data = null): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_path = get_stylesheet_directory() . '/debug_zebricek.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] V2: $message";
        
        if ($data !== null) {
            $log_entry .= "\n" . print_r($data, true);
        }
        
        file_put_contents($log_path, $log_entry . "\n\n", FILE_APPEND);
    }
    
    /**
     * Override sanitize_attributes
     */
    protected function sanitize_attributes(array $attributes): array
    {
        $sanitized = parent::sanitize_attributes($attributes);
        
        $sanitized['limit'] = max(1, min(100, absint($sanitized['limit'])));
        $sanitized['offset'] = max(0, absint($sanitized['offset']));
        $sanitized['show_numbers'] = in_array($sanitized['show_numbers'], ['true', 'false'], true) ? $sanitized['show_numbers'] : 'true';
        $sanitized['show_pagination'] = in_array($sanitized['show_pagination'], ['true', 'false'], true) ? $sanitized['show_pagination'] : 'true';
        $sanitized['year'] = !empty($sanitized['year']) ? absint($sanitized['year']) : date('Y');
        
        return $sanitized;
    }
}