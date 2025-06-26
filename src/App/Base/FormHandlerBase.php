<?php

declare(strict_types=1);

namespace MistrFachman\Base;

use MistrFachman\Services\UserDetectionService;

/**
 * Base Form Handler - Abstract Foundation
 *
 * Provides common patterns for handling Contact Form 7 submissions.
 * Separates generic post creation and file upload logic from domain-specific field mappings.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class FormHandlerBase {

    public function __construct(
        protected UserDetectionService $user_detection_service,
        protected $manager // Base manager interface - will be typed properly in DI-Lite phase
    ) {}

    /**
     * Get the form ID to match for this domain
     */
    abstract protected function getFormId(): int;

    /**
     * Get the post type slug for this domain
     */
    abstract protected function getPostType(): string;

    /**
     * Get the display name for this domain (for logging)
     */
    abstract protected function getDomainDisplayName(): string;

    /**
     * Map form fields to post arguments
     * Should return array with keys: post_title, post_content, etc.
     */
    abstract protected function mapFormDataToPost(array $posted_data): array;

    /**
     * Save domain-specific ACF/meta fields
     */
    abstract protected function saveDomainFields(int $post_id, array $posted_data): void;

    /**
     * Get the gallery field name from form data
     */
    abstract protected function getGalleryFieldName(): string;

    /**
     * Save gallery data using domain-specific field service
     */
    abstract protected function saveGalleryData(int $post_id, array $gallery_ids): bool;

    /**
     * Populate initial post meta data immediately after post creation
     * This ensures points and other calculated fields are available from the start
     */
    abstract protected function populate_initial_post_meta(int $post_id, array $posted_data): void;

    /**
     * Main handler for WPCF7 form submissions
     *
     * @param \WPCF7_ContactForm $contact_form The WPCF7 form object
     */
    public function handle_submission(\WPCF7_ContactForm $contact_form): void {
        $domain_debug = strtoupper($this->getPostType());
        
        // Add debug logging for form submission attempts
        error_log("[{$domain_debug}:DEBUG] Form submission attempt - Form ID: " . $contact_form->id() . ", Title: " . $contact_form->title());
        
        $form_id = $contact_form->id();
        $form_title = $contact_form->title();
        
        // Debug: Log form details
        error_log("[{$domain_debug}:DEBUG] Form details - ID: {$form_id}, Title: {$form_title}");
        
        // Check if this is the correct form by ID
        if ((int)$contact_form->id() !== $this->getFormId()) {
            error_log("[{$domain_debug}:DEBUG] Form ID mismatch - Expected: {$this->getFormId()}, Got: {$contact_form->id()}");
            return;
        }
        
        error_log("[{$domain_debug}:DEBUG] Form matched - proceeding with submission processing");

        $submission = \WPCF7_Submission::get_instance();
        if (!$submission) {
            error_log("[{$domain_debug}:DEBUG] No CF7 submission instance available");
            return;
        }

        $posted_data = $submission->get_posted_data();
        $uploaded_files = $submission->uploaded_files();

        // Use UserDetectionService for proper user detection in CF7 context
        $user_id = $this->user_detection_service->detectUser($posted_data);
        if (!$user_id) {
            error_log("[{$domain_debug}:DEBUG] User not detected - aborting (tried session, cookie, and form data)");
            return; 
        }

        // Validate user permissions
        if (!$this->validateUserPermissions($user_id, $domain_debug)) {
            return;
        }

        error_log("[{$domain_debug}:DEBUG] Processing submission for user ID: {$user_id}");
        error_log("[{$domain_debug}:DEBUG] Posted data fields: " . implode(', ', array_keys($posted_data)));
        
        // Check if post type exists
        if (!post_type_exists($this->getPostType())) {
            error_log("[{$domain_debug}:ERROR] Post type \"{$this->getPostType()}\" does not exist");
            return;
        }
        
        error_log("[{$domain_debug}:DEBUG] Post type \"{$this->getPostType()}\" confirmed to exist");

        // Create the post using domain-specific mapping
        $post_id = $this->createPost($posted_data, $user_id, $domain_debug);
        if (!$post_id) {
            return;
        }

        // Save domain-specific fields
        error_log("[{$domain_debug}:DEBUG] Saving meta data for post ID: {$post_id}");
        $this->saveDomainFields($post_id, $posted_data);

        // New step: Immediately populate meta fields after post creation
        $this->populate_initial_post_meta($post_id, $posted_data);
        error_log("[{$domain_debug}:SUCCESS] Initial meta population complete for post ID: {$post_id}");

        // Handle file uploads
        $this->handleFileUploads($post_id, $posted_data, $uploaded_files, $domain_debug);
        
        error_log("[{$domain_debug}:SUCCESS] Form processing complete for post ID: {$post_id} - Default points will be auto-populated by PostTypeManagerBase hook");
    }

    /**
     * Validate user permissions to submit forms of this type
     */
    protected function validateUserPermissions(int $user_id, string $domain_debug): bool {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log("[{$domain_debug}:ERROR] User object not found for ID: {$user_id}");
            return false;
        }

        $user_roles = $user->roles;
        $allowed_roles = ['full_member', 'administrator'];
        $has_permission = !empty(array_intersect($user_roles, $allowed_roles));

        if (!$has_permission) {
            error_log("[{$domain_debug}:DEBUG] User lacks permission - User roles: " . implode(', ', $user_roles) . " | Required: " . implode(' or ', $allowed_roles));
            return false;
        }

        error_log("[{$domain_debug}:DEBUG] User authorized - ID: {$user_id} | Roles: " . implode(', ', $user_roles));
        return true;
    }

    /**
     * Create post with mapped data
     */
    protected function createPost(array $posted_data, int $user_id, string $domain_debug): ?int {
        // Get domain-specific field mappings
        $mapped_data = $this->mapFormDataToPost($posted_data);
        
        // Create base post arguments
        $post_args = [
            'post_type'    => $this->getPostType(),
            'post_status'  => 'pending',
            'post_author'  => $user_id,
        ];
        
        // Merge with domain-specific mappings
        $post_args = array_merge($post_args, $mapped_data);
        
        error_log("[{$domain_debug}:DEBUG] Attempting to create post with args: " . wp_json_encode($post_args));
        $post_id = wp_insert_post($post_args);

        if (is_wp_error($post_id)) {
            error_log("[{$domain_debug}:ERROR] Failed to create post - WP Error: " . $post_id->get_error_message());
            return null;
        } elseif ($post_id === 0) {
            error_log("[{$domain_debug}:ERROR] Failed to create post - wp_insert_post returned 0");
            return null;
        }
        
        error_log("[{$domain_debug}:SUCCESS] Created post with ID: {$post_id}");
        return $post_id;
    }

    /**
     * Handle file uploads (generic implementation)
     */
    protected function handleFileUploads(int $post_id, array $posted_data, array $uploaded_files, string $domain_debug): void {
        $gallery_field_name = $this->getGalleryFieldName();
        
        error_log("[{$domain_debug}:DEBUG] Processing file uploads");
        error_log("[{$domain_debug}:DEBUG] Uploaded files structure: " . wp_json_encode($uploaded_files));
        error_log("[{$domain_debug}:DEBUG] Posted data structure for files: " . wp_json_encode($posted_data[$gallery_field_name] ?? 'not set'));
        
        $gallery_ids = [];
        
        // Check if files are in posted_data (for drag-drop plugin compatibility)
        $files_data = null;
        if (!empty($posted_data[$gallery_field_name])) {
            $files_data = $posted_data[$gallery_field_name];
            error_log("[{$domain_debug}:DEBUG] Files found in posted_data: " . wp_json_encode($files_data));
        } elseif (!empty($uploaded_files) && isset($uploaded_files[$gallery_field_name])) {
            $files_data = $uploaded_files[$gallery_field_name];
            error_log("[{$domain_debug}:DEBUG] Files found in uploaded_files: " . wp_json_encode($files_data));
        }
        
        if ($files_data) {
            $files = $files_data;
            
            // Handle both single file and array of files
            if (!is_array($files)) {
                $files = [$files];
            }
            
            error_log("[{$domain_debug}:DEBUG] Found " . count($files) . " uploaded files");
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            foreach ($files as $index => $file_url) {
                if (!empty($file_url)) {
                    error_log("[{$domain_debug}:DEBUG] Processing file " . ($index + 1) . ": {$file_url}");
                    
                    // Check if it's a URL (from drag-drop plugin) or local path
                    if (filter_var($file_url, FILTER_VALIDATE_URL)) {
                        $attachment_id = $this->process_file_url($file_url, $post_id);
                    } else {
                        // Local file path - use existing method
                        $attachment_id = $this->upload_and_attach_file($file_url, $post_id);
                    }
                    
                    if ($attachment_id) {
                        $gallery_ids[] = $attachment_id;
                        error_log("[{$domain_debug}:DEBUG] File processed successfully with attachment ID: {$attachment_id}");
                    } else {
                        error_log("[{$domain_debug}:DEBUG] File processing failed for: {$file_url}");
                    }
                } else {
                    error_log("[{$domain_debug}:DEBUG] Skipping empty file entry");
                }
            }
        } else {
            error_log("[{$domain_debug}:DEBUG] No files uploaded or {$gallery_field_name} field empty");
        }
        
        if (!empty($gallery_ids)) {
            $result = $this->saveGalleryData($post_id, $gallery_ids);
            error_log("[{$domain_debug}:DEBUG] Gallery field update result: " . ($result ? 'success' : 'failed') . " with " . count($gallery_ids) . " images");
        }
    }

    /**
     * Helper function to process a single file upload from WPCF7
     * @param string $file_path Temporary path to the uploaded file
     * @param int $post_id The ID of the post to attach the file to
     * @return int|null The new attachment ID on success, null on failure
     */
    protected function upload_and_attach_file(string $file_path, int $post_id): ?int {
        $file_name = basename($file_path);
        $upload = wp_upload_bits($file_name, null, file_get_contents($file_path));

        if ($upload['error']) {
            error_log('[FILE_UPLOAD:ERROR] wp_upload_bits failed: ' . $upload['error']);
            return null;
        }

        $file_path_wp = $upload['file'];
        $file_url = $upload['url'];

        $attachment = [
            'post_mime_type' => wp_check_filetype($file_name)['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', $file_name),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id
        ];

        $attachment_id = wp_insert_attachment($attachment, $file_path_wp, $post_id);

        if (is_wp_error($attachment_id)) {
            error_log('[FILE_UPLOAD:ERROR] wp_insert_attachment failed: ' . $attachment_id->get_error_message());
            return null;
        }

        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path_wp);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        error_log('[FILE_UPLOAD:SUCCESS] File uploaded with attachment ID: ' . $attachment_id);
        return $attachment_id;
    }

    /**
     * Process file from URL (for drag-drop plugins)
     * @param string $file_url URL to the file
     * @param int $post_id The ID of the post to attach the file to
     * @return int|null The new attachment ID on success, null on failure
     */
    protected function process_file_url(string $file_url, int $post_id): ?int {
        // Download the file
        $file_content = wp_remote_get($file_url);
        
        if (is_wp_error($file_content)) {
            error_log('[FILE_URL:ERROR] Failed to download file: ' . $file_content->get_error_message());
            return null;
        }
        
        $file_body = wp_remote_retrieve_body($file_content);
        $file_name = basename(parse_url($file_url, PHP_URL_PATH));
        
        // If no filename in URL, generate one
        if (empty($file_name) || !pathinfo($file_name, PATHINFO_EXTENSION)) {
            $file_name = 'upload_' . time() . '.jpg'; // Default to jpg
        }
        
        $upload = wp_upload_bits($file_name, null, $file_body);
        
        if ($upload['error']) {
            error_log('[FILE_URL:ERROR] wp_upload_bits failed: ' . $upload['error']);
            return null;
        }
        
        $file_path_wp = $upload['file'];
        
        $attachment = [
            'post_mime_type' => wp_check_filetype($file_name)['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', $file_name),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $file_path_wp, $post_id);
        
        if (is_wp_error($attachment_id)) {
            error_log('[FILE_URL:ERROR] wp_insert_attachment failed: ' . $attachment_id->get_error_message());
            return null;
        }
        
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path_wp);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        error_log('[FILE_URL:SUCCESS] File processed from URL with attachment ID: ' . $attachment_id);
        return $attachment_id;
    }
}