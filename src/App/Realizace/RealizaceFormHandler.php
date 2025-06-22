<?php
declare(strict_types=1);
namespace MistrFachman\Realizace;

use MistrFachman\Services\UserDetectionService;

class RealizaceFormHandler {

    public function __construct(
        private UserDetectionService $user_detection_service
    ) {}

    /**
     * Handles the WPCF7 form submission to create a 'realizace' post.
     *
     * @param \WPCF7_ContactForm $contact_form The WPCF7 form object.
     */
    public function handle_submission(\WPCF7_ContactForm $contact_form): void {
        // Add debug logging for form submission attempts
        error_log('[REALIZACE:DEBUG] Form submission attempt - Form ID: ' . $contact_form->id() . ', Title: ' . $contact_form->title());
        
        // The shortcode uses a hash ID, but we need the actual post ID
        // Let's check by form title or find the correct numeric ID
        $form_id = $contact_form->id();
        $form_title = $contact_form->title();
        
        // Debug: Log form details
        error_log('[REALIZACE:DEBUG] Form details - ID: ' . $form_id . ', Title: ' . $form_title);
        
        // Check if this is the realizace form by title (more reliable than ID)
        if ($form_title !== 'Přidat realizaci') {
            error_log('[REALIZACE:DEBUG] Form title mismatch - Expected: "Přidat realizaci", Got: "' . $form_title . '"');
            return;
        }
        
        error_log('[REALIZACE:DEBUG] Form matched - proceeding with submission processing');

        $submission = \WPCF7_Submission::get_instance();
        if (!$submission) {
            error_log('[REALIZACE:DEBUG] No CF7 submission instance available');
            return;
        }

        $posted_data = $submission->get_posted_data();
        $uploaded_files = $submission->uploaded_files();

        // Use UserDetectionService for proper user detection in CF7 context
        $user_id = $this->user_detection_service->detectUser($posted_data);
        if (!$user_id) {
            error_log('[REALIZACE:DEBUG] User not detected - aborting (tried session, cookie, and form data)');
            return; 
        }

        // Check if user has permission to submit realizace forms
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log('[REALIZACE:ERROR] User object not found for ID: ' . $user_id);
            return;
        }

        $user_roles = $user->roles;
        $allowed_roles = ['full_member', 'administrator'];
        $has_permission = !empty(array_intersect($user_roles, $allowed_roles));

        if (!$has_permission) {
            error_log('[REALIZACE:DEBUG] User lacks permission - User roles: ' . implode(', ', $user_roles) . ' | Required: ' . implode(' or ', $allowed_roles));
            return;
        }

        error_log('[REALIZACE:DEBUG] User authorized - ID: ' . $user_id . ' | Roles: ' . implode(', ', $user_roles));
        
        error_log('[REALIZACE:DEBUG] Processing submission for user ID: ' . $user_id);
        error_log('[REALIZACE:DEBUG] Posted data fields: ' . implode(', ', array_keys($posted_data)));
        
        // Check if realizace post type exists
        if (!post_type_exists('realizace')) {
            error_log('[REALIZACE:ERROR] Post type "realizace" does not exist');
            return;
        }
        
        error_log('[REALIZACE:DEBUG] Post type "realizace" confirmed to exist');

        // Create the post
        $post_args = [
            'post_type'    => 'realizace',
            'post_title'   => sanitize_text_field($posted_data['project-title'] ?? ''),
            'post_content' => sanitize_textarea_field($posted_data['popis_projektu'] ?? ''),
            'post_status'  => 'pending',
            'post_author'  => $user_id,
        ];
        
        error_log('[REALIZACE:DEBUG] Attempting to create post with args: ' . wp_json_encode($post_args));
        $post_id = wp_insert_post($post_args);

        if (is_wp_error($post_id)) {
            error_log('[REALIZACE:ERROR] Failed to create realizace post - WP Error: ' . $post_id->get_error_message());
            return;
        } elseif ($post_id === 0) {
            error_log('[REALIZACE:ERROR] Failed to create realizace post - wp_insert_post returned 0');
            return;
        }
        
        error_log('[REALIZACE:SUCCESS] Created realizace post with ID: ' . $post_id);

        // Save ACF/Meta data
        error_log('[REALIZACE:DEBUG] Saving meta data for post ID: ' . $post_id);
        
        if (isset($posted_data['pocet_m2'])) {
            $result = update_field('pocet_m2', (int)$posted_data['pocet_m2'], $post_id);
            error_log('[REALIZACE:DEBUG] pocet_m2 field update result: ' . ($result ? 'success' : 'failed') . ' | Value: ' . (int)$posted_data['pocet_m2']);
        }
        
        if (isset($posted_data['typ_konstrukce'])) {
            $result = update_field('typ_konstrukce', sanitize_textarea_field($posted_data['typ_konstrukce']), $post_id);
            error_log('[REALIZACE:DEBUG] typ_konstrukce field update result: ' . ($result ? 'success' : 'failed') . ' | Value: ' . substr($posted_data['typ_konstrukce'], 0, 50) . '...');
        }
        
        if (isset($posted_data['pouzite_materialy'])) {
            $result = update_field('pouzite_materialy', sanitize_textarea_field($posted_data['pouzite_materialy']), $post_id);
            error_log('[REALIZACE:DEBUG] pouzite_materialy field update result: ' . ($result ? 'success' : 'failed') . ' | Value: ' . substr($posted_data['pouzite_materialy'], 0, 50) . '...');
        }

        // Handle file uploads
        error_log('[REALIZACE:DEBUG] Processing file uploads');
        error_log('[REALIZACE:DEBUG] Uploaded files structure: ' . wp_json_encode($uploaded_files));
        error_log('[REALIZACE:DEBUG] Posted data structure for files: ' . wp_json_encode($posted_data['fotky_realizace'] ?? 'not set'));
        
        $gallery_ids = [];
        
        // Check if files are in posted_data (for drag-drop plugin compatibility)
        $files_data = null;
        if (!empty($posted_data['fotky_realizace'])) {
            $files_data = $posted_data['fotky_realizace'];
            error_log('[REALIZACE:DEBUG] Files found in posted_data: ' . wp_json_encode($files_data));
        } elseif (!empty($uploaded_files) && isset($uploaded_files['fotky_realizace'])) {
            $files_data = $uploaded_files['fotky_realizace'];
            error_log('[REALIZACE:DEBUG] Files found in uploaded_files: ' . wp_json_encode($files_data));
        }
        
        if ($files_data) {
            $files = $files_data;
            
            // Handle both single file and array of files
            if (!is_array($files)) {
                $files = [$files];
            }
            
            error_log('[REALIZACE:DEBUG] Found ' . count($files) . ' uploaded files');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            foreach ($files as $index => $file_url) {
                if (!empty($file_url)) {
                    error_log('[REALIZACE:DEBUG] Processing file ' . ($index + 1) . ': ' . $file_url);
                    
                    // Check if it's a URL (from drag-drop plugin) or local path
                    if (filter_var($file_url, FILTER_VALIDATE_URL)) {
                        $attachment_id = $this->process_file_url($file_url, $post_id);
                    } else {
                        // Local file path - use existing method
                        $attachment_id = $this->upload_and_attach_file($file_url, $post_id);
                    }
                    
                    if ($attachment_id) {
                        $gallery_ids[] = $attachment_id;
                        error_log('[REALIZACE:DEBUG] File processed successfully with attachment ID: ' . $attachment_id);
                    } else {
                        error_log('[REALIZACE:DEBUG] File processing failed for: ' . $file_url);
                    }
                } else {
                    error_log('[REALIZACE:DEBUG] Skipping empty file entry');
                }
            }
        } else {
            error_log('[REALIZACE:DEBUG] No files uploaded or fotky_realizace field empty');
        }
        
        if (!empty($gallery_ids)) {
            $result = update_field('fotky_realizace', $gallery_ids, $post_id);
            error_log('[REALIZACE:DEBUG] Gallery field update result: ' . ($result ? 'success' : 'failed') . ' with ' . count($gallery_ids) . ' images');
        }
        
        error_log('[REALIZACE:SUCCESS] Form processing complete for post ID: ' . $post_id);
    }

    /**
     * Helper function to process a single file upload from WPCF7.
     * @param string $file_path Temporary path to the uploaded file.
     * @param int $post_id The ID of the post to attach the file to.
     * @return int|null The new attachment ID on success, null on failure.
     */
    private function upload_and_attach_file(string $file_path, int $post_id): ?int {
        $file_name = basename($file_path);
        $upload = wp_upload_bits($file_name, null, file_get_contents($file_path));

        if (!empty($upload['error'])) {
            error_log('File upload error: ' . $upload['error']);
            return null;
        }

        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', $file_name),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        if (is_wp_error($attachment_id)) {
            error_log('Attachment creation error: ' . $attachment_id->get_error_message());
            return null;
        }
        
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        return $attachment_id;
    }

    /**
     * Process a file URL from the drag-drop plugin
     * @param string $file_url URL to the uploaded file
     * @param int $post_id The ID of the post to attach the file to
     * @return int|null The attachment ID if it exists, null on failure
     */
    private function process_file_url(string $file_url, int $post_id): ?int {
        error_log('[REALIZACE:DEBUG] Processing file URL: ' . $file_url);
        
        // Extract the file path from the URL
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        
        // Check if this URL is from our upload directory
        if (strpos($file_url, $base_url) === 0) {
            $relative_path = str_replace($base_url, '', $file_url);
            $file_path = $upload_dir['basedir'] . $relative_path;
            
            error_log('[REALIZACE:DEBUG] Converted URL to local path: ' . $file_path);
            
            // Check if file exists
            if (file_exists($file_path)) {
                // Check if attachment already exists in WordPress
                $attachment_id = $this->get_attachment_id_by_url($file_url);
                
                if ($attachment_id) {
                    error_log('[REALIZACE:DEBUG] Found existing attachment ID: ' . $attachment_id);
                    // Update attachment parent to link it to our post
                    wp_update_post([
                        'ID' => $attachment_id,
                        'post_parent' => $post_id
                    ]);
                    return $attachment_id;
                } else {
                    // Create new attachment record
                    error_log('[REALIZACE:DEBUG] Creating new attachment record for existing file');
                    return $this->create_attachment_from_existing_file($file_path, $post_id);
                }
            } else {
                error_log('[REALIZACE:DEBUG] File does not exist at: ' . $file_path);
            }
        } else {
            error_log('[REALIZACE:DEBUG] File URL not from our upload directory: ' . $file_url);
        }
        
        return null;
    }

    /**
     * Get attachment ID by URL
     * @param string $url File URL
     * @return int|null Attachment ID if found, null otherwise
     */
    private function get_attachment_id_by_url(string $url): ?int {
        $attachment_id = attachment_url_to_postid($url);
        return $attachment_id > 0 ? $attachment_id : null;
    }

    /**
     * Create attachment record for existing file
     * @param string $file_path Local file path
     * @param int $post_id Parent post ID
     * @return int|null Attachment ID on success, null on failure
     */
    private function create_attachment_from_existing_file(string $file_path, int $post_id): ?int {
        $file_name = basename($file_path);
        $upload_dir = wp_upload_dir();
        
        // Get file type
        $file_type = wp_check_filetype($file_name, null);
        
        // Prepare attachment data
        $attachment = [
            'post_mime_type' => $file_type['type'],
            'post_title'     => preg_replace('/\\.[^.]+$/', '', $file_name),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id
        ];

        // Insert the attachment
        $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);
        
        if (is_wp_error($attachment_id)) {
            error_log('[REALIZACE:DEBUG] Attachment creation error: ' . $attachment_id->get_error_message());
            return null;
        }
        
        // Generate attachment metadata
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        return $attachment_id;
    }
}