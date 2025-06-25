/**
 * Status Dropdown Manager - Generic Admin Status UI Management
 *
 * Handles custom status dropdown functionality for admin post edit pages.
 * Supports any domain's custom status through configuration.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

export class StatusDropdownManager {
    constructor(config) {
        this.config = {
            domain: config.domain || 'unknown',
            postType: config.postType || 'post',
            customStatus: config.customStatus || 'rejected',
            customStatusLabel: config.customStatusLabel || 'Rejected',
            currentStatus: config.currentStatus || null,
            debugPrefix: config.debugPrefix || 'DOMAIN'
        };

        this.init();
    }

    init() {
        if (!this.isValidContext()) {
            return;
        }

        this.log('Initializing status dropdown support');
        this.setupStatusDropdown();
        this.handleCurrentStatus();
        this.bindEvents();
        this.setupGutenbergSupport();
        this.setupFormSubmissionHandler();
    }

    isValidContext() {
        // Only run on post edit pages for our specific post type
        const urlParams = new URLSearchParams(window.location.search);
        const postType = urlParams.get('post_type') ||
                        document.querySelector('input[name="post_type"]')?.value;

        return postType === this.config.postType;
    }

    setupStatusDropdown() {
        const $statusSelect = jQuery('select[name="post_status"]');
        this.log('Found status select elements:', $statusSelect.length);

        if ($statusSelect.length && !$statusSelect.find(`option[value="${this.config.customStatus}"]`).length) {
            $statusSelect.append(`<option value="${this.config.customStatus}">${this.config.customStatusLabel}</option>`);
            this.log(`Added ${this.config.customStatus} option to status dropdown`);
        }
    }

    handleCurrentStatus() {
        if (this.config.currentStatus === this.config.customStatus) {
            this.log(`Setting current status to ${this.config.customStatus}`);

            const $statusSelect = jQuery('select[name="post_status"]');
            $statusSelect.val(this.config.customStatus);
            jQuery('#post-status-display').text(this.config.customStatusLabel);

            // Also update any hidden inputs
            jQuery('input[name="_status"]').val(this.config.customStatus);
            this.log(`Updated hidden status input to ${this.config.customStatus}`);
        }
    }

    bindEvents() {
        const $statusSelect = jQuery('select[name="post_status"]');

        $statusSelect.on('change', (event) => {
            const selectedStatus = jQuery(event.target).val();
            const displayText = jQuery(event.target).find('option:selected').text();

            this.log('Status changed to:', selectedStatus);
            jQuery('#post-status-display').text(displayText);

            // Ensure hidden input is also updated
            jQuery('input[name="_status"]').val(selectedStatus);
        });
    }

    setupGutenbergSupport() {
        // Fix for Gutenberg editor
        if (window.wp && window.wp.data && window.wp.data.select('core/editor')) {
            const postStatus = window.wp.data.select('core/editor').getEditedPostAttribute('status');
            this.log('Gutenberg editor detected, current status:', postStatus);

            if (postStatus === this.config.customStatus) {
                jQuery('.editor-post-status .components-select-control__input').val(this.config.customStatus);
                this.log(`Updated Gutenberg status to ${this.config.customStatus}`);
            }
        }
    }

    setupFormSubmissionHandler() {
        jQuery('#post').on('submit', () => {
            const currentStatus = jQuery('select[name="post_status"]').val();
            this.log('Form submitting with status:', currentStatus);

            if (currentStatus === this.config.customStatus) {
                // Ensure the POST data includes the custom status
                if (!jQuery(`input[name="post_status"][value="${this.config.customStatus}"]`).length) {
                    jQuery('#post').append(`<input type="hidden" name="post_status" value="${this.config.customStatus}">`);
                    this.log(`Added hidden input for ${this.config.customStatus} status`);
                }
            }
        });
    }

    log(...args) {
        console.log(`[${this.config.debugPrefix}:UI]`, ...args);
    }
}

// Note: Initialization now handled by AdminApp class
// No auto-initialization needed - module is imported and instantiated properly
