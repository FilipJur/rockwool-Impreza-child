<?php

declare(strict_types=1);

namespace MistrFachman\Users;

use MistrFachman\Services\UserService;

/**
 * Theme Integration Class
 *
 * Handles integration with WordPress theme features including
 * body classes and other frontend modifications based on user status.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ThemeIntegration {

    public function __construct(
        private UserService $user_service
    ) {}

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks(): void {
        add_filter('body_class', [$this, 'add_user_status_body_class']);
        add_action('wp_head', [$this, 'add_custom_css_variables']);
    }

    /**
     * Add user status body class for CSS targeting
     *
     * @param array $classes Existing body classes
     * @return array Modified body classes
     */
    public function add_user_status_body_class(array $classes): array {
        $status = $this->user_service->get_user_registration_status();
        
        if (!empty($status)) {
            $classes[] = $this->user_service->get_status_css_class($status);
            
            // Add additional context classes
            if ($this->user_service->can_user_purchase()) {
                $classes[] = 'can-purchase';
            } else {
                $classes[] = 'cannot-purchase';
            }
            
            if ($this->user_service->can_user_view_products()) {
                $classes[] = 'can-view-products';
            }
        }

        return $classes;
    }

    /**
     * Add CSS custom properties for user status styling
     */
    public function add_custom_css_variables(): void {
        $status = $this->user_service->get_user_registration_status();
        
        if (empty($status)) {
            return;
        }

        echo '<style id="mistr-fachman-user-vars">';
        echo ':root {';
        echo '--user-status: "' . esc_attr($status) . '";';
        echo '--user-status-display: "' . esc_attr($this->user_service->get_status_display_name($status)) . '";';
        echo '--can-purchase: ' . ($this->user_service->can_user_purchase() ? '1' : '0') . ';';
        echo '--can-view-products: ' . ($this->user_service->can_user_view_products() ? '1' : '0') . ';';
        echo '}';
        echo '</style>';
    }

    /**
     * Get user status for use in templates
     *
     * @return array User status information
     */
    public function get_user_status_info(): array {
        $status = $this->user_service->get_user_registration_status();
        
        return [
            'status' => $status,
            'display_name' => $this->user_service->get_status_display_name($status),
            'css_class' => $this->user_service->get_status_css_class($status),
            'can_purchase' => $this->user_service->can_user_purchase(),
            'can_view_products' => $this->user_service->can_user_view_products(),
        ];
    }

    /**
     * Display user status badge (for use in templates)
     *
     * @param bool $echo Whether to echo or return the output
     * @return string The badge HTML (empty string if logged out)
     */
    public function display_user_status_badge(bool $echo = true): string {
        $info = $this->get_user_status_info();
        
        if ($info['status'] === 'logged_out') {
            $output = '';
        } else {
            $output = sprintf(
                '<span class="user-status-badge %s" data-status="%s">%s</span>',
                esc_attr($info['css_class']),
                esc_attr($info['status']),
                esc_html($info['display_name'])
            );
        }

        if ($echo) {
            echo $output;
            return $output;
        } else {
            return $output;
        }
    }
}