<?php

declare(strict_types=1);

namespace MistrFachman\Users;

use MistrFachman\Services\UserService;

/**
 * Access Control Class
 *
 * Manages page-level access restrictions based on user login status
 * and registration state. Redirects unauthorized users appropriately.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AccessControl {

    private array $allowed_pages_for_logged_out = [
        'homepage',
        'lp-result',
        'registrace',
        'prihlaseni'
    ];

    public function __construct(
        private UserService $user_service
    ) {}

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks(): void {
        // Only run on frontend template requests, not API calls
        add_action('template_redirect', [$this, 'check_page_access'], 5);
    }

    /**
     * Check if current page is accessible to current user
     */
    public function check_page_access(): void {
        // Early return for any non-frontend requests
        if ($this->should_skip_access_check()) {
            return;
        }

        $user_status = $this->user_service->get_user_registration_status();

        // Handle logged-out users
        if ($user_status === 'logged_out') {
            $this->handle_logged_out_access();
            return;
        }

        // Handle logged-in users based on their status
        $this->handle_logged_in_access($user_status);
    }

    /**
     * Handle access control for logged-out users
     */
    private function handle_logged_out_access(): void {
        $current_page = $this->get_current_page_identifier();

        // Allow access to specific pages
        if (in_array($current_page, $this->allowed_pages_for_logged_out, true)) {
            return;
        }

        // Allow access to homepage (root)
        if (is_front_page() || is_home()) {
            return;
        }

        // Redirect to registration page
        $registration_url = home_url('/prihlaseni');

        mycred_debug('Redirecting logged-out user from restricted page', [
            'current_page' => $current_page,
            'redirect_to' => $registration_url,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], 'access_control', 'info');

        wp_redirect($registration_url);
        exit;
    }

    /**
     * Handle access control for logged-in users
     */
    private function handle_logged_in_access(string $user_status): void {
        // For now, logged-in users can access all pages
        // This can be extended later for status-specific restrictions

        mycred_debug('Logged-in user accessing page', [
            'user_status' => $user_status,
            'page' => $this->get_current_page_identifier(),
            'user_id' => get_current_user_id()
        ], 'access_control', 'verbose');
    }

    /**
     * Get current page identifier
     */
    private function get_current_page_identifier(): string {
        global $post;

        // Handle homepage
        if (is_front_page() || is_home()) {
            return 'homepage';
        }

        // Handle specific pages by slug
        if (is_page()) {
            return get_post_field('post_name', $post) ?: 'unknown-page';
        }

        // Handle other page types
        if (is_single()) {
            return 'single-post';
        }

        if (is_category()) {
            return 'category';
        }

        if (is_archive()) {
            return 'archive';
        }

        if (is_search()) {
            return 'search';
        }

        if (is_404()) {
            return '404';
        }

        return 'unknown';
    }

    /**
     * Check if current page is allowed for logged-out users
     */
    public function is_current_page_allowed_for_logged_out(): bool {
        $current_page = $this->get_current_page_identifier();

        return is_front_page() ||
               is_home() ||
               in_array($current_page, $this->allowed_pages_for_logged_out, true);
    }

    /**
     * Get allowed pages for logged-out users
     */
    public function get_allowed_pages_for_logged_out(): array {
        return $this->allowed_pages_for_logged_out;
    }

    /**
     * Add allowed page for logged-out users
     */
    public function add_allowed_page_for_logged_out(string $page_slug): void {
        if (!in_array($page_slug, $this->allowed_pages_for_logged_out, true)) {
            $this->allowed_pages_for_logged_out[] = $page_slug;

            mycred_debug('Added allowed page for logged-out users', [
                'page_slug' => $page_slug,
                'allowed_pages' => $this->allowed_pages_for_logged_out
            ], 'access_control', 'info');
        }
    }

    /**
     * Remove allowed page for logged-out users
     */
    public function remove_allowed_page_for_logged_out(string $page_slug): void {
        $key = array_search($page_slug, $this->allowed_pages_for_logged_out, true);

        if ($key !== false) {
            unset($this->allowed_pages_for_logged_out[$key]);
            $this->allowed_pages_for_logged_out = array_values($this->allowed_pages_for_logged_out);

            mycred_debug('Removed allowed page for logged-out users', [
                'page_slug' => $page_slug,
                'allowed_pages' => $this->allowed_pages_for_logged_out
            ], 'access_control', 'info');
        }
    }

    /**
     * Comprehensive check if we should skip access control
     */
    private function should_skip_access_check(): bool {
        // Skip admin pages, AJAX, cron, and API requests
        if (is_admin() ||
            wp_doing_ajax() ||
            wp_doing_cron() ||
            (defined('REST_REQUEST') && REST_REQUEST) ||
            (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return true;
        }

        // Check for REST API requests by URL
        if ($this->is_rest_api_request()) {
            return true;
        }

        // Skip if this is an internal operation
        if ($this->is_internal_operation()) {
            return true;
        }

        // Skip if we're not in a template context (safety check)
        if (!did_action('template_redirect')) {
            return true;
        }

        return false;
    }

    /**
     * Check if current request is a REST API request
     */
    private function is_rest_api_request(): bool {
        // Check if we're in a REST API request
        $rest_prefix = rest_get_url_prefix();
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        return strpos($request_uri, "/$rest_prefix/") !== false;
    }

    /**
     * Check if this is an internal operation that should not be blocked
     */
    private function is_internal_operation(): bool {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Allow all POST requests to wp-json endpoints
        if ($request_method === 'POST' && strpos($request_uri, '/wp-json/') !== false) {
            return true;
        }

        // Allow Contact Form 7 endpoints
        if (strpos($request_uri, '/wp-json/contact-form-7/') !== false) {
            return true;
        }

        // Allow WooCommerce endpoints
        if (strpos($request_uri, '/wp-json/wc/') !== false) {
            return true;
        }

        // Allow any form submissions (POST requests)
        if ($request_method === 'POST') {
            return true;
        }

        return false;
    }
}
