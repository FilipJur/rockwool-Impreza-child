<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Admin Asset Manager - Styles & Scripts
 *
 * Manages CSS and JavaScript assets for realizace admin interface.
 * Provides centralized asset management with proper enqueueing and localization.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminAssetManager {

    /**
     * Enqueue admin assets for realizace management
     */
    public function enqueue_admin_assets(): void {
        // Only load on user edit screens
        if (!$this->is_user_edit_screen()) {
            return;
        }

        $this->enqueue_admin_styles();
        $this->enqueue_admin_scripts();
        $this->localize_admin_scripts();
    }

    /**
     * Enqueue admin styles
     */
    private function enqueue_admin_styles(): void {
        $css_file = get_stylesheet_directory() . '/assets/css/realizace-admin.css';
        
        if (file_exists($css_file)) {
            // Use external CSS file
            wp_enqueue_style(
                'realizace-admin-css',
                get_stylesheet_directory_uri() . '/assets/css/realizace-admin.css',
                ['admin-bar'],
                filemtime($css_file)
            );
        } else {
            // Fallback to inline styles
            wp_add_inline_style('admin-bar', $this->get_admin_css());
        }
    }

    /**
     * Enqueue admin scripts
     */
    private function enqueue_admin_scripts(): void {
        // Check if the main admin.js build exists
        $main_admin_js = get_stylesheet_directory() . '/build/js/admin.js';
        
        if (file_exists($main_admin_js)) {
            // Use the built admin.js which includes realizace management
            wp_enqueue_script(
                'mistr-admin-js',
                get_stylesheet_directory_uri() . '/build/js/admin.js',
                [],
                filemtime($main_admin_js),
                true
            );
            // Prevent inline script conflicts
            wp_add_inline_script('mistr-admin-js', 'window.skipRealizaceFallback = true;', 'before');
        } else {
            // Fallback: Try standalone realizace admin file
            $js_file = get_stylesheet_directory() . '/assets/js/realizace-admin.js';
            
            if (file_exists($js_file)) {
                wp_enqueue_script(
                    'realizace-admin-js',
                    get_stylesheet_directory_uri() . '/assets/js/realizace-admin.js',
                    ['jquery'],
                    filemtime($js_file),
                    true
                );
            } else {
                // Final fallback to inline script
                wp_add_inline_script('jquery', $this->get_admin_javascript());
            }
        }
    }

    /**
     * Localize admin scripts with AJAX data
     */
    private function localize_admin_scripts(): void {
        $main_admin_js = get_stylesheet_directory() . '/build/js/admin.js';
        $standalone_js = get_stylesheet_directory() . '/assets/js/realizace-admin.js';
        
        // Determine which script handle to use for localization
        $script_handle = 'jquery'; // Default fallback
        
        if (file_exists($main_admin_js)) {
            $script_handle = 'mistr-admin-js';
        } elseif (file_exists($standalone_js)) {
            $script_handle = 'realizace-admin-js';
        }
        
        wp_localize_script(
            $script_handle,
            'mistrRealizaceAdmin',
            $this->get_localized_data()
        );
    }

    /**
     * Check if current screen is user edit page
     */
    private function is_user_edit_screen(): bool {
        $screen = get_current_screen();
        return $screen && in_array($screen->id, ['user-edit', 'profile']);
    }

    /**
     * Get admin CSS styles
     */
    public function get_admin_css(): string {
        return '
        /* Realizace Admin Interface Styles */
        .realizace-management-section {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .realizace-overview .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            border-radius: 6px;
            background: #f8f9fa;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .stat-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        
        .stat-pending { border-left: 4px solid #f59e0b; }
        .stat-approved { border-left: 4px solid #10b981; }
        .stat-rejected { border-left: 4px solid #ef4444; }
        .stat-points { border-left: 4px solid #8b5cf6; }
        
        .realizace-list {
            space-y: 10px;
        }
        
        .realizace-item {
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #ffffff;
            margin-bottom: 10px;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .item-title {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
        }
        
        .item-title a {
            text-decoration: none;
            color: #1e293b;
        }
        
        .item-title a:hover {
            color: #3b82f6;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        
        .status-success .badge-indicator { background-color: #10b981; }
        .status-warning .badge-indicator { background-color: #f59e0b; }
        .status-error .badge-indicator { background-color: #ef4444; }
        
        .item-meta {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .meta-points {
            color: #8b5cf6;
            font-weight: 500;
        }
        
        .item-actions {
            display: flex;
            gap: 8px;
        }
        
        .action-link {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            background: none;
        }
        
        .action-edit { color: #3b82f6; }
        .action-approve { color: #10b981; }
        .action-reject { color: #ef4444; }
        
        .action-link:hover {
            background-color: #f1f5f9;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .action-button {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }
        
        .action-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .action-primary { background: #3b82f6; color: white; }
        .action-secondary { background: #64748b; color: white; }
        .action-warning { background: #f59e0b; color: white; }
        .action-info { background: #06b6d4; color: white; }
        
        .action-notice {
            margin-top: 12px;
            padding: 8px;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 4px;
        }
        
        .notice-text {
            font-size: 12px;
            color: #92400e;
            margin: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 20px;
            color: #64748b;
        }
        
        .empty-message {
            margin: 0;
            font-style: italic;
        }
        ';
    }

    /**
     * Get admin JavaScript code
     */
    public function get_admin_javascript(): string {
        $quick_action_nonce = wp_create_nonce('mistr_fachman_realizace_action');
        $bulk_approve_nonce = wp_create_nonce('mistr_fachman_bulk_approve');

        return "
        jQuery(document).ready(function($) {
            // Skip if main admin.js is already handling this
            if (window.skipRealizaceFallback || window.realizaceManagementInitialized) {
                console.log('Realizace fallback script skipped - main admin.js handling events');
                return;
            }
            
            // Debug: Check if elements exist
            console.log('Realizace admin fallback script loaded');
            console.log('Found action buttons:', $('.action-approve, .action-reject').length);
            
            // Handle quick approve/reject actions
            $(document).on('click', '.action-approve, .action-reject', function(e) {
                e.preventDefault();
                
                var \$btn = $(this);
                var postId = \$btn.data('post-id');
                var action = \$btn.data('action');
                var actionText = action === 'approve' ? 'schválit' : 'odmítnout';
                
                console.log('Button clicked:', {postId: postId, action: action});
                
                if (!confirm('Opravdu chcete ' + actionText + ' tuto realizaci?')) {
                    return;
                }
                
                \$btn.prop('disabled', true).text('Zpracovává se...');
                
                var ajaxData = {
                    action: 'mistr_fachman_realizace_quick_action',
                    post_id: postId,
                    realizace_action: action,
                    nonce: '{$quick_action_nonce}'
                };
                
                console.log('AJAX data:', ajaxData);
                console.log('AJAX URL:', ajaxurl);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: ajaxData,
                    success: function(response) {
                        console.log('AJAX success:', response);
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Chyba: ' + response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('AJAX error:', {xhr: xhr, status: status, error: error});
                        alert('Došlo k chybě při zpracování požadavku: ' + error);
                    },
                    complete: function() {
                        \$btn.prop('disabled', false).text(actionText === 'schválit' ? 'Schválit' : 'Odmítnout');
                    }
                });
            });
            
            // Handle bulk approve
            $('.bulk-approve-btn').on('click', function(e) {
                e.preventDefault();
                
                var \$btn = $(this);
                var userId = \$btn.data('user-id');
                
                if (!confirm('Opravdu chcete hromadně schválit všechny čekající realizace tohoto uživatele?')) {
                    return;
                }
                
                \$btn.prop('disabled', true).text('Zpracovává se...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mistr_fachman_bulk_approve_realizace',
                        user_id: userId,
                        nonce: '{$bulk_approve_nonce}'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Chyba: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Došlo k chybě při zpracování požadavku.');
                    },
                    complete: function() {
                        \$btn.prop('disabled', false).text('Hromadně schválit');
                    }
                });
            });
        });
        ";
    }

    /**
     * Get localized script data for AJAX requests
     */
    public function get_localized_data(): array {
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonces' => [
                'quick_action' => wp_create_nonce('mistr_fachman_realizace_action'),
                'bulk_approve' => wp_create_nonce('mistr_fachman_bulk_approve')
            ],
            'messages' => [
                'confirm_approve' => __('Opravdu chcete schválit tuto realizaci?', 'mistr-fachman'),
                'confirm_reject' => __('Opravdu chcete odmítnout tuto realizaci?', 'mistr-fachman'),
                'confirm_bulk_approve' => __('Opravdu chcete hromadně schválit všechny čekající realizace tohoto uživatele?', 'mistr-fachman'),
                'processing' => __('Zpracovává se...', 'mistr-fachman'),
                'error_generic' => __('Došlo k chybě při zpracování požadavku.', 'mistr-fachman'),
                'approve_text' => __('Schválit', 'mistr-fachman'),
                'reject_text' => __('Odmítnout', 'mistr-fachman'),
                'bulk_approve_text' => __('Hromadně schválit', 'mistr-fachman')
            ]
        ];
    }
}