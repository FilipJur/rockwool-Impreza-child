<?php

declare(strict_types=1);

namespace MistrFachman\Base;

/**
 * Base Form Handler - Abstract Foundation
 *
 * Provides common patterns for Contact Form 7 integration and post creation.
 * Handles file uploads, ACF field population, and user assignment.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class FormHandlerBase {

    /**
     * Get the post type slug for this domain
     */
    abstract protected function getPostType(): string;

    /**
     * Get the display name for this domain
     */
    abstract protected function getDomainDisplayName(): string;

    /**
     * Get the Contact Form 7 form ID for this domain
     */
    abstract protected function getFormId(): int;

    /**
     * Get the default points for this domain
     */
    abstract protected function getDefaultPoints(int $post_id = 0): int;

    /**
     * Get the points field name
     */
    abstract protected function getPointsFieldName(): string;

    /**
     * Process domain-specific form data
     */
    abstract protected function process_form_data(array $form_data, int $post_id): void;

    /**
     * Validate domain-specific form data
     */
    abstract protected function validate_form_data(array $form_data): array;

    /**
     * Initialize form handling hooks
     */
    public function init_hooks(): void {
        add_action('wpcf7_before_send_mail', [$this, 'handle_form_submission']);
        add_action('wp_ajax_' . $this->getPostType() . '_form_submit', [$this, 'handle_ajax_submission']);
        add_action('wp_ajax_nopriv_' . $this->getPostType() . '_form_submit', [$this, 'handle_ajax_submission']);
    }

    /**
     * Handle Contact Form 7 submission
     */
    public function handle_form_submission(\WPCF7_ContactForm $contact_form): void {
        // Only handle submissions for our specific form
        if ($contact_form->id() !== $this->getFormId()) {
            return;
        }

        error_log("[{$this->getPostType()}:FORM] Processing form submission for form ID {$this->getFormId()}");

        $submission = \WPCF7_Submission::get_instance();
        if (!$submission) {
            error_log("[{$this->getPostType()}:FORM] No submission instance found");
            return;
        }

        $posted_data = $submission->get_posted_data();
        
        try {
            // Validate form data
            $validation_errors = $this->validate_form_data($posted_data);
            if (!empty($validation_errors)) {
                error_log("[{$this->getPostType()}:FORM] Validation errors: " . implode(', ', $validation_errors));
                return;
            }

            // Get the current user
            $user_id = $this->get_current_user_id();
            if (!$user_id) {
                error_log("[{$this->getPostType()}:FORM] No valid user found for submission");
                return;
            }

            // Create the post
            $post_id = $this->create_post($posted_data, $user_id);
            if (!$post_id) {
                error_log("[{$this->getPostType()}:FORM] Failed to create post");
                return;
            }

            // Process domain-specific data
            $this->process_form_data($posted_data, $post_id);

            // Set default points if needed
            $this->ensure_default_points($post_id);

            error_log("[{$this->getPostType()}:FORM] Successfully created post {$post_id} for user {$user_id}");

        } catch (\Exception $e) {
            error_log("[{$this->getPostType()}:FORM] Exception during form processing: " . $e->getMessage());
        }
    }

    /**
     * Handle AJAX form submission
     */
    public function handle_ajax_submission(): void {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', $this->getPostType() . '_form_nonce')) {
                wp_send_json_error(['message' => 'Invalid nonce']);
            }

            $user_id = $this->get_current_user_id();
            if (!$user_id) {
                wp_send_json_error(['message' => 'User not authenticated']);
            }

            $form_data = $_POST;

            // Validate form data
            $validation_errors = $this->validate_form_data($form_data);
            if (!empty($validation_errors)) {
                wp_send_json_error(['message' => 'Validation failed', 'errors' => $validation_errors]);
            }

            // Create post
            $post_id = $this->create_post($form_data, $user_id);
            if (!$post_id) {
                wp_send_json_error(['message' => 'Failed to create post']);
            }

            // Process domain-specific data
            $this->process_form_data($form_data, $post_id);

            // Set default points
            $this->ensure_default_points($post_id);

            wp_send_json_success([
                'message' => ucfirst($this->getDomainDisplayName()) . ' byla úspěšně odeslána',
                'post_id' => $post_id
            ]);

        } catch (\Exception $e) {
            error_log("[{$this->getPostType()}:AJAX] Exception: " . $e->getMessage());
            wp_send_json_error(['message' => 'Chyba při zpracování formuláře']);
        }
    }

    /**
     * Create the post from form data
     */
    protected function create_post(array $form_data, int $user_id): int {
        $title = $this->generate_post_title($form_data, $user_id);
        $content = $this->generate_post_content($form_data);

        $post_data = [
            'post_type' => $this->getPostType(),
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'pending',
            'post_author' => $user_id,
            'meta_input' => [
                '_submission_date' => current_time('mysql'),
                '_form_id' => $this->getFormId()
            ]
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            error_log("[{$this->getPostType()}:FORM] Failed to create post: " . $post_id->get_error_message());
            return 0;
        }

        return $post_id;
    }

    /**
     * Generate post title from form data
     */
    protected function generate_post_title(array $form_data, int $user_id): string {
        $user = get_userdata($user_id);
        $user_name = $user ? $user->display_name : "User {$user_id}";
        $date = date('j.n.Y');
        
        return sprintf('%s - %s (%s)', $this->getDomainDisplayName(), $user_name, $date);
    }

    /**
     * Generate post content from form data
     */
    protected function generate_post_content(array $form_data): string {
        $content_parts = [];
        
        // Add submission timestamp
        $content_parts[] = 'Odesláno: ' . current_time('j.n.Y H:i:s');
        
        // Add form data (excluding files and sensitive data)
        foreach ($form_data as $key => $value) {
            if (!in_array($key, ['_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag']) && 
                !str_starts_with($key, '_') && 
                !empty($value) && 
                !is_array($value)) {
                $content_parts[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . sanitize_text_field($value);
            }
        }
        
        return implode("\n", $content_parts);
    }

    /**
     * Get current user ID with session fallback
     */
    protected function get_current_user_id(): int {
        // Try standard WordPress user
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            // Try session-based detection for logged-out scenarios
            if (class_exists('MistrFachman\\Services\\UserDetectionService')) {
                $detection_service = new \MistrFachman\Services\UserDetectionService();
                $user_id = $detection_service->get_current_user_id();
            }
        }

        return $user_id;
    }

    /**
     * Handle file uploads for a post
     */
    protected function handle_file_uploads(array $files, int $post_id, string $field_name): array {
        if (empty($files) || !is_array($files)) {
            return [];
        }

        $uploaded_files = [];
        
        foreach ($files as $file) {
            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                continue;
            }

            $upload_result = $this->upload_file($file, $post_id);
            if ($upload_result) {
                $uploaded_files[] = $upload_result;
            }
        }

        // Store file references in ACF field
        if (!empty($uploaded_files) && function_exists('update_field')) {
            update_field($field_name, $uploaded_files, $post_id);
        }

        return $uploaded_files;
    }

    /**
     * Upload a single file
     */
    protected function upload_file(array $file, int $post_id): ?array {
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $upload_overrides = [
            'test_form' => false,
            'test_size' => true
        ];

        $uploaded_file = wp_handle_upload($file, $upload_overrides);

        if (!empty($uploaded_file['error'])) {
            error_log("[{$this->getPostType()}:FORM] File upload error: " . $uploaded_file['error']);
            return null;
        }

        // Create attachment
        $attachment_data = [
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $post_id
        ];

        $attachment_id = wp_insert_attachment($attachment_data, $uploaded_file['file'], $post_id);

        if (is_wp_error($attachment_id)) {
            error_log("[{$this->getPostType()}:FORM] Failed to create attachment: " . $attachment_id->get_error_message());
            return null;
        }

        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);

        return [
            'ID' => $attachment_id,
            'url' => $uploaded_file['url'],
            'type' => $uploaded_file['type'],
            'title' => $attachment_data['post_title']
        ];
    }

    /**
     * Ensure post has default points set
     */
    protected function ensure_default_points(int $post_id): void {
        $current_points = $this->get_current_points($post_id);
        
        if ($current_points === 0) {
            $default_points = $this->getDefaultPoints($post_id);
            
            if ($default_points > 0) {
                $this->set_points($post_id, $default_points);
                
                error_log("[{$this->getPostType()}:FORM] Set default points: {$default_points} for post {$post_id}");
            }
        }
    }

    /**
     * Get current points value
     */
    protected function get_current_points(int $post_id): int {
        if (function_exists('get_field')) {
            $points = get_field($this->getPointsFieldName(), $post_id);
        } else {
            $points = get_post_meta($post_id, $this->getPointsFieldName(), true);
        }

        return is_numeric($points) ? (int)$points : 0;
    }

    /**
     * Set points value
     */
    protected function set_points(int $post_id, int $points): bool {
        if (function_exists('update_field')) {
            $result = update_field($this->getPointsFieldName(), $points, $post_id);
            return $result !== false;
        } else {
            return update_post_meta($post_id, $this->getPointsFieldName(), $points) !== false;
        }
    }
}