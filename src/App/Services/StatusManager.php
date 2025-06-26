<?php

declare(strict_types=1);

namespace MistrFachman\Services;

/**
 * Unified Status Manager Service
 *
 * Handles registration and management of custom post statuses for any domain.
 * This service consolidates all status management logic into a single, configurable class.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

final class StatusManager {

    private string $post_type;
    private string $custom_status_slug;
    private string $custom_status_label;

    public function __construct(string $post_type, string $custom_status_slug, string $custom_status_label) {
        $this->post_type = $post_type;
        $this->custom_status_slug = $custom_status_slug;
        $this->custom_status_label = $custom_status_label;
    }

    /**
     * Get the custom status arguments for registration
     * Override in child classes to customize status behavior
     */
    protected function getCustomStatusArgs(): array {
        return [
            'label' => $this->custom_status_label,
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'protected' => true,
            'private' => true,
            'publicly_queryable' => false,
            '_builtin' => false,
            'label_count' => _n_noop(
                $this->custom_status_label . ' <span class="count">(%s)</span>',
                $this->custom_status_label . ' <span class="count">(%s)</span>'
            ),
        ];
    }

    /**
     * Initialize status management hooks
     */
    public function init_hooks(): void {
        // Multiple registration attempts to ensure status is available
        add_action('plugins_loaded', [$this, 'register_custom_status'], 1);
        add_action('init', [$this, 'register_custom_status'], 1);
        add_action('wp_loaded', [$this, 'verify_custom_status'], 1);
        add_filter('display_post_states', [$this, 'add_post_state_labels'], 10, 2);
        
        // Add custom status to post editor dropdown
        add_action('admin_footer-post.php', [$this, 'add_custom_status_to_dropdown']);
        add_action('admin_footer-post-new.php', [$this, 'add_custom_status_to_dropdown']);
    }

    /**
     * Register the custom post status with smart timing detection
     */
    public function register_custom_status(): void {
        static $registered = false;
        
        $status_slug = $this->custom_status_slug;
        $domain_debug = strtoupper($this->post_type);
        
        // Prevent multiple registrations
        if ($registered) {
            error_log("[{$domain_debug}:STATUS] {$status_slug} status already registered, skipping...");
            return;
        }
        
        error_log("[{$domain_debug}:STATUS] Registering {$status_slug} status...");
        error_log("[{$domain_debug}:STATUS] Current hook: " . (current_action() ?: 'none'));
        error_log("[{$domain_debug}:STATUS] WordPress loaded: " . (did_action('wp_loaded') ? 'yes' : 'no'));
        
        // Check if status already exists (from another source)
        $existing_statuses = get_post_stati();
        if (isset($existing_statuses[$status_slug])) {
            error_log("[{$domain_debug}:STATUS] {$status_slug} status already exists, marking as registered");
            $registered = true;
            return;
        }
        
        $status_args = $this->getCustomStatusArgs();
        error_log("[{$domain_debug}:STATUS] {$status_slug} status args: " . json_encode($status_args));
        
        $result = register_post_status($status_slug, $status_args);
        error_log("[{$domain_debug}:STATUS] register_post_status returned: " . json_encode($result));
        
        // Verify registration immediately
        $registered_statuses = get_post_stati();
        if (isset($registered_statuses[$status_slug])) {
            error_log("[{$domain_debug}:STATUS] SUCCESS: {$status_slug} status confirmed in registered statuses");
            $registered = true;
        } else {
            error_log("[{$domain_debug}:STATUS] ERROR: {$status_slug} status NOT found after registration");
            error_log("[{$domain_debug}:STATUS] Available statuses: " . implode(', ', array_keys($registered_statuses)));
        }
    }

    /**
     * Verify custom status registration after WordPress is fully loaded
     */
    public function verify_custom_status(): void {
        $status_slug = $this->custom_status_slug;
        $domain_debug = strtoupper($this->post_type);
        
        error_log("[{$domain_debug}:STATUS] Verifying {$status_slug} status after wp_loaded...");
        
        $registered_statuses = get_post_stati();
        $available_keys = array_keys($registered_statuses);
        
        error_log("[{$domain_debug}:STATUS] All registered statuses: " . implode(', ', $available_keys));
        
        if (isset($registered_statuses[$status_slug])) {
            error_log("[{$domain_debug}:STATUS] SUCCESS: {$status_slug} status found in registration");
            $custom_status = $registered_statuses[$status_slug];
            
            // Check if it's an object or string
            if (is_object($custom_status)) {
                error_log("[{$domain_debug}:STATUS] Properties: " . json_encode([
                    'label' => property_exists($custom_status, 'label') ? $custom_status->label : 'not set',
                    'public' => property_exists($custom_status, 'public') ? $custom_status->public : 'not set',
                    'show_in_admin_all_list' => property_exists($custom_status, 'show_in_admin_all_list') ? $custom_status->show_in_admin_all_list : 'not set',
                    'show_in_admin_status_list' => property_exists($custom_status, 'show_in_admin_status_list') ? $custom_status->show_in_admin_status_list : 'not set'
                ]));
            } else {
                error_log("[{$domain_debug}:STATUS] Status is not an object, type: " . gettype($custom_status) . ', value: ' . json_encode($custom_status));
            }
        } else {
            error_log("[{$domain_debug}:STATUS] CRITICAL: {$status_slug} status NOT found after wp_loaded!");
            error_log("[{$domain_debug}:STATUS] This will cause AJAX reject actions to fail");
        }
    }

    /**
     * Add custom post state labels in admin list view
     *
     * @param array $post_states Current post states
     * @param \WP_Post $post Post object
     * @return array Modified post states
     */
    public function add_post_state_labels(array $post_states, \WP_Post $post): array {
        if ($post->post_type !== $this->post_type) {
            return $post_states;
        }

        $status_slug = $this->custom_status_slug;
        if ($post->post_status === $status_slug) {
            $post_states[$status_slug] = $this->custom_status_label;
        }

        return $post_states;
    }

    /**
     * Check if custom status is available for post updates
     *
     * @return bool True if custom status is available
     */
    public function is_custom_status_available(): bool {
        $available_statuses = get_post_stati(['show_in_admin_status_list' => true]);
        return isset($available_statuses[$this->custom_status_slug]);
    }

    /**
     * Emergency re-registration of custom status
     * Used as fallback when status is missing during AJAX operations
     *
     * @return bool True if re-registration succeeded
     */
    public function emergency_register_custom_status(): bool {
        $status_slug = $this->custom_status_slug;
        $domain_debug = strtoupper($this->post_type);
        
        error_log("[{$domain_debug}:STATUS] EMERGENCY: Attempting re-registration...");
        
        $this->register_custom_status();
        
        // Check if it worked
        $available_statuses = get_post_stati(['show_in_admin_status_list' => true]);
        $success = isset($available_statuses[$status_slug]);
        
        if ($success) {
            error_log("[{$domain_debug}:STATUS] EMERGENCY: Re-registration succeeded!");
        } else {
            error_log("[{$domain_debug}:STATUS] EMERGENCY: Re-registration FAILED!");
        }
        
        return $success;
    }

    /**
     * Validate custom status implementation
     * 
     * @param int|null $post_id Optional post ID to check specific post
     * @return array Diagnostic information
     */
    public function validate_status_implementation(?int $post_id = null): array {
        $status_slug = $this->custom_status_slug;
        $domain_debug = strtoupper($this->post_type);
        
        error_log("[{$domain_debug}:STATUS] Starting status validation");
        
        $diagnostics = [
            'status_registered' => false,
            'available_statuses' => [],
            'specific_post' => null,
            'issues' => [],
        ];
        
        // Check if custom status is properly registered
        $registered_statuses = get_post_stati();
        $diagnostics['available_statuses'] = array_keys($registered_statuses);
        
        if (isset($registered_statuses[$status_slug])) {
            $diagnostics['status_registered'] = true;
            error_log("[{$domain_debug}:STATUS] Validation: {$status_slug} status is registered");
        } else {
            $diagnostics['issues'][] = "{$status_slug} status not registered";
            error_log("[{$domain_debug}:STATUS] Validation: ERROR - {$status_slug} status not registered");
        }
        
        // Check specific post if provided
        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                $diagnostics['specific_post'] = [
                    'id' => $post->ID,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'exists' => true
                ];
                
                if ($post->post_status === $status_slug) {
                    error_log("[{$domain_debug}:STATUS] Validation: Post {$post_id} has {$status_slug} status correctly");
                } else {
                    error_log("[{$domain_debug}:STATUS] Validation: Post {$post_id} status is {$post->post_status}");
                }
            } else {
                $diagnostics['specific_post'] = ['exists' => false];
                $diagnostics['issues'][] = "Post {$post_id} does not exist";
                error_log("[{$domain_debug}:STATUS] Validation: ERROR - Post {$post_id} does not exist");
            }
        }
        
        error_log("[{$domain_debug}:STATUS] Validation complete: " . json_encode($diagnostics));
        return $diagnostics;
    }

    /**
     * Add custom status to post editor dropdown via JavaScript
     */
    public function add_custom_status_to_dropdown(): void {
        global $post;
        
        // Only add to posts of our specific post type
        if (!$post || $post->post_type !== $this->post_type) {
            return;
        }
        
        $status_slug = $this->custom_status_slug;
        $status_label = $this->custom_status_label;
        $current_status = $post->post_status;
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add custom status to the status dropdown
            var $statusSelect = $('select[name="post_status"]');
            if ($statusSelect.length && !$statusSelect.find('option[value="<?php echo esc_js($status_slug); ?>"]').length) {
                $statusSelect.append('<option value="<?php echo esc_js($status_slug); ?>"><?php echo esc_js($status_label); ?></option>');
            }
            
            // Set the correct status if this post is already rejected
            <?php if ($current_status === $status_slug): ?>
            $statusSelect.val('<?php echo esc_js($status_slug); ?>');
            $('#post-status-display').text('<?php echo esc_js($status_label); ?>');
            
            // Also update hidden status input
            $('input[name="_status"]').val('<?php echo esc_js($status_slug); ?>');
            <?php endif; ?>
        });
        </script>
        <?php
    }
}