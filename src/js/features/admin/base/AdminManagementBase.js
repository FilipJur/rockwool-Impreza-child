/**
 * Admin Management Base Class
 * 
 * Abstract foundation for domain-specific admin management classes.
 * Provides common functionality for AJAX requests, notifications, and UI management.
 * Enables 80% code reuse for new domains like Faktury.
 * 
 * @abstract
 * @since 1.0.0
 */

export class AdminManagementBase {
  /**
   * Constructor for admin management instances
   * 
   * @param {Object} config - Configuration object
   * @param {string} config.containerSelector - CSS selector for management container
   * @param {Object} config.globalData - Localized WordPress data
   */
  constructor(config) {
    if (this.constructor === AdminManagementBase) {
      throw new Error('AdminManagementBase is an abstract class and cannot be instantiated directly');
    }
    
    this.config = config;
    this.state = {
      containers: [],
      activeRequests: new Set(),
      isInitialized: false
    };
    
    // Bind methods to maintain context
    this.handleQuickAction = this.handleQuickAction.bind(this);
    this.handleBulkAction = this.handleBulkAction.bind(this);
    this.handleSaveRejection = this.handleSaveRejection.bind(this);
    this.handleSectionToggle = this.handleSectionToggle.bind(this);
    
    this.init();
  }

  /**
   * Initialize the management interface
   * Sets up containers and event listeners
   */
  init() {
    if (this.state.isInitialized) {
      console.warn(`${this.getDomainName()} management already initialized`);
      return;
    }

    this.state.containers = document.querySelectorAll(this.config.containerSelector);
    
    if (this.state.containers.length === 0) {
      console.log(`No ${this.getDomainName()} management containers found`);
      return;
    }

    console.log(`${this.getDomainName()} management initialized with ${this.state.containers.length} container(s)`);
    
    this.setupEventListeners();
    this.populateDefaultValues();
    this.makePointsInputReadOnly();
    this.setupCollapsibleSections();
    this.state.isInitialized = true;
  }

  /**
   * Set up event listeners for management interface
   * Uses event delegation for better performance
   */
  setupEventListeners() {
    console.log(`[${this.getDomainName()}] Setting up event listeners...`);
    
    // Scope event listeners to this domain's containers only
    this.state.containers.forEach(container => {
      // Quick action buttons (approve/reject)
      container.addEventListener('click', this.handleQuickAction);
      
      // Bulk actions
      container.addEventListener('click', this.handleBulkAction);
      
      // Save rejection reason
      container.addEventListener('click', this.handleSaveRejection);
      
      // Section toggle (collapsible sections)
      container.addEventListener('click', this.handleSectionToggle);
    });
    
    console.log(`[${this.getDomainName()}] ✅ Quick action event listener added`);
    console.log(`[${this.getDomainName()}] ✅ Bulk action event listener added`);
    console.log(`[${this.getDomainName()}] ✅ Save rejection event listener added`);
    console.log(`[${this.getDomainName()}] ✅ Section toggle event listener added`);
    
    console.log(`[${this.getDomainName()}] All event listeners registered successfully`);
  }

  /**
   * Populate default values in form inputs
   * Uses domain-specific default values
   */
  populateDefaultValues() {
    const defaultValues = this.getDefaultValues();
    
    if (defaultValues.points) {
      const pointsInputs = document.querySelectorAll('.quick-points-input[data-post-id]');
      pointsInputs.forEach(input => {
        if (!input.value || input.value === '0' || input.value === '') {
          input.value = defaultValues.points.toString();
        }
      });
    }
  }

  /**
   * Make points input fields read-only to prevent admin confusion
   * Points are calculated automatically and should not be manually edited
   */
  makePointsInputReadOnly() {
    this.state.containers.forEach(container => {
      const pointsInputs = container.querySelectorAll('.quick-points-input');
      pointsInputs.forEach(input => {
        input.setAttribute('readonly', true);
        input.style.backgroundColor = '#f0f0f1'; // Visual cue for disabled state
        input.style.cursor = 'not-allowed';
        input.title = 'Toto pole je pouze pro čtení a je spravováno automaticky.';
      });
    });
  }

  /**
   * Handle quick action button clicks (approve/reject)
   * 
   * @param {Event} event - Click event
   */
  async handleQuickAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button || !['approve', 'reject'].includes(button.dataset.action)) return;

    event.preventDefault();
    
    const postId = button.dataset.postId;
    const action = button.dataset.action;
    
    if (!postId || !action) {
      console.error('Missing post ID or action data');
      return;
    }

    const actionText = action === 'approve' ? 'schválit' : 'odmítnout';
    const messages = this.getMessages();
    const confirmMessage = action === 'approve' ? 
      messages.confirm_approve : messages.confirm_reject;
    
    if (!confirm(confirmMessage || `Opravdu chcete ${actionText} tento záznam?`)) {
      return;
    }

    // Get points value for approval - find the input associated with the clicked button
    const itemContainer = button.closest('.realization-item, .invoice-item'); // Find the parent card
    let points = null;
    if (action === 'approve' && itemContainer) {
        const pointsInput = itemContainer.querySelector(`.quick-points-input[data-post-id="${postId}"]`);
        if (pointsInput) {
            points = pointsInput.value;
            console.log(`[${this.getDomainName()}] Found points input with value: ${points} for post ${postId}`);
        }
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = messages.processing || 'Zpracovává se...';

    try {
      const response = await this.makeAjaxRequest(this.config.getEndpoint('quickAction'), {
        post_id: postId,
        [`${this.getPostType()}_action`]: action,
        points: points,
        nonce: this.getNonce('quick_action')
      });

      if (response.success) {
        this.showNotification(response.data.message, 'success');
        this.reloadPage();
      } else {
        this.showNotification(`Chyba: ${response.data.message}`, 'error');
      }
    } catch (error) {
      console.error('Quick action error:', error);
      this.showNotification('Došlo k chybě při zpracování požadavku.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = originalText;
    }
  }

  /**
   * Handle bulk action button clicks
   * 
   * @param {Event} event - Click event
   */
  async handleBulkAction(event) {
    console.log('[AdminManagementBase] handleBulkAction called, event target:', event.target);
    const button = event.target.closest('.bulk-approve-btn, .bulk-reject-btn');
    console.log('[AdminManagementBase] Found button:', button);
    if (!button) {
      console.log('[AdminManagementBase] No bulk action button found, ignoring click');
      return;
    }

    event.preventDefault();
    
    const userId = button.dataset.userId;
    const action = button.classList.contains('bulk-approve-btn') ? 'approve' : 'reject';
    
    if (!userId) {
      console.error('Missing user ID data');
      return;
    }

    const messages = this.getMessages();
    const confirmMessage = messages.confirm_bulk_approve || 
      `Opravdu chcete hromadně ${action === 'approve' ? 'schválit' : 'odmítnout'} všechny čekající záznamy tohoto uživatele?`;
    
    if (!confirm(confirmMessage)) {
      return;
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = messages.processing || 'Zpracovává se...';

    try {
      const requestData = {
        user_id: userId,
        bulk_action: action,
        nonce: this.getNonce('bulk_approve')
      };
      
      console.log('[AdminManagementBase] Bulk action request data:', requestData);
      console.log('[AdminManagementBase] Endpoint:', this.config.getEndpoint('bulkAction'));
      
      const response = await this.makeAjaxRequest(this.config.getEndpoint('bulkAction'), requestData);

      if (response.success) {
        this.showNotification(response.data.message, 'success');
        this.reloadPage();
      } else {
        this.showNotification(`Chyba: ${response.data.message}`, 'error');
      }
    } catch (error) {
      console.error('Bulk action error:', error);
      this.showNotification('Došlo k chybě při zpracování požadavku.', 'error');
    } finally {
      button.disabled = false;
      button.textContent = originalText;
    }
  }

  /**
   * Handle save rejection reason button clicks
   * 
   * @param {Event} event - Click event
   */
  async handleSaveRejection(event) {
    const button = event.target.closest('.save-rejection-btn');
    if (!button) return;

    event.preventDefault();
    
    const postId = button.dataset.postId;
    if (!postId) {
      console.error('Missing post ID data');
      return;
    }

    const textarea = document.querySelector(`.rejection-reason-input[data-post-id="${postId}"]`);
    if (!textarea) {
      console.error('Rejection reason textarea not found');
      return;
    }

    const rejectionReason = textarea.value.trim();
    if (!rejectionReason) {
      this.showNotification('Zadejte prosím důvod odmítnutí.', 'warning');
      return;
    }

    const requestId = `rejection-${postId}-${Date.now()}`;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Ukládá se...';

    this.state.activeRequests.add(requestId);

    try {
      const fieldNames = this.getFieldNames();
      const response = await this.makeAjaxRequest('mistr_fachman_update_acf_field', {
        post_id: postId,
        field_name: fieldNames.rejection_reason,
        field_value: rejectionReason,
        nonce: this.getNonce('quick_action')
      });

      if (response.success) {
        this.showNotification('Důvod odmítnutí byl uložen.', 'success');
        button.textContent = 'Uloženo ✓';
        setTimeout(() => {
          button.textContent = originalText;
        }, 2000);
      } else {
        this.showNotification(`Chyba: ${response.data.message}`, 'error');
      }
    } catch (error) {
      console.error('Save rejection error:', error);
      this.showNotification('Došlo k chybě při ukládání.', 'error');
    } finally {
      this.state.activeRequests.delete(requestId);
      button.disabled = false;
      if (button.textContent === 'Ukládá se...') {
        button.textContent = originalText;
      }
    }
  }

  /**
   * Setup collapsible sections with proper default states
   * Business rules:
   * - Pending: Not collapsible (no toggle button)
   * - Rejected: Collapsible but expanded by default 
   * - Approved: Collapsible and collapsed by default
   */
  setupCollapsibleSections() {
    this.state.containers.forEach(container => {
      const sections = container.querySelectorAll(`.${this.getDomainSlug()}-section`);
      
      sections.forEach(section => {
        const status = section.dataset.status || section.className.match(/section-(\w+)/)?.[1];
        const content = section.querySelector('.section-content.collapsible');
        const toggleButton = section.querySelector('.section-toggle');
        const icon = toggleButton?.querySelector('.toggle-icon');
        
        if (!content || !status) return;
        
        // Apply business rules based on status
        switch (status) {
          case 'pending':
            // Pending sections: Not collapsible, hide toggle button
            if (toggleButton) {
              toggleButton.style.display = 'none';
            }
            content.classList.remove('collapsed');
            break;
            
          case 'rejected':
            // Rejected sections: Collapsible but expanded by default
            if (toggleButton) {
              toggleButton.style.display = 'block';
            }
            content.classList.remove('collapsed');
            if (icon) {
              icon.textContent = '▼';
            }
            break;
            
          case 'approved':
            // Approved sections: Collapsible and collapsed by default
            if (toggleButton) {
              toggleButton.style.display = 'block';
            }
            content.classList.add('collapsed');
            if (icon) {
              icon.textContent = '▶';
            }
            break;
        }
      });
    });
  }

  /**
   * Handle section toggle button clicks (collapsible sections)
   * 
   * @param {Event} event - Click event
   */
  handleSectionToggle(event) {
    const toggleButton = event.target.closest('.section-toggle');
    if (!toggleButton) return;

    event.preventDefault();
    
    const section = toggleButton.closest(`.${this.getDomainSlug()}-section`);
    if (!section) return;

    const content = section.querySelector('.section-content.collapsible');
    const icon = toggleButton.querySelector('.toggle-icon');
    
    if (content && icon) {
      content.classList.toggle('collapsed');
      icon.textContent = content.classList.contains('collapsed') ? '▶' : '▼';
    }
  }

  /**
   * Make AJAX request with error handling
   * 
   * @param {string} action - WordPress AJAX action
   * @param {Object} data - Request data
   * @returns {Promise<Object>} Response data
   */
  async makeAjaxRequest(action, data) {
    const ajaxUrl = this.config.globalData.ajax_url || window.ajaxurl;
    if (!ajaxUrl) {
      throw new Error('AJAX URL not available');
    }

    console.log('[AJAX:DEBUG] Making request to:', ajaxUrl);
    console.log('[AJAX:DEBUG] Action:', action);
    console.log('[AJAX:DEBUG] Data:', data);

    const formData = new FormData();
    formData.append('action', action);
    
    Object.entries(data).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        formData.append(key, value);
      }
    });

    // Log all FormData entries
    console.log('[AJAX:DEBUG] FormData contents:');
    for (let [key, value] of formData.entries()) {
      console.log(`[AJAX:DEBUG]   ${key}: ${value}`);
    }

    const response = await fetch(ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });

    console.log('[AJAX:DEBUG] Response status:', response.status);
    console.log('[AJAX:DEBUG] Response headers:', [...response.headers.entries()]);

    if (!response.ok) {
      const responseText = await response.text();
      console.error('[AJAX:DEBUG] Error response body:', responseText);
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const result = await response.json();
    console.log('[AJAX:DEBUG] Response JSON:', result);
    return result;
  }

  /**
   * Show notification to user
   * 
   * @param {string} message - Notification message
   * @param {string} type - Notification type (success, error, info, warning)
   */
  showNotification(message, type = 'info') {
    // Use WordPress admin notices if available
    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
      wp.data.dispatch('core/notices').createNotice(type, message, {
        isDismissible: true,
        type: type
      });
      return;
    }

    // Fallback to simple alert
    console.log(`${type.toUpperCase()}: ${message}`);
    alert(message);
  }

  /**
   * Reload the current page
   */
  reloadPage() {
    window.location.reload();
  }

  /**
   * Get nonce for specific action
   * 
   * @param {string} action - Nonce action name
   * @returns {string} Nonce value
   */
  getNonce(action) {
    return this.config.globalData.nonces?.[action] || '';
  }

  /**
   * Cleanup event listeners and state
   */
  cleanup() {
    document.removeEventListener('click', this.handleQuickAction);
    document.removeEventListener('click', this.handleBulkAction);
    document.removeEventListener('click', this.handleSaveRejection);
    document.removeEventListener('click', this.handleSectionToggle);
    
    this.state.activeRequests.clear();
    this.state.containers = [];
    this.state.isInitialized = false;
  }

  /**
   * Check if management is initialized
   * 
   * @returns {boolean} Initialization status
   */
  isInitialized() {
    return this.state.isInitialized;
  }

  // Abstract methods - must be implemented by subclasses

  /**
   * Get domain name for logging
   * 
   * @abstract
   * @returns {string} Domain display name
   */
  getDomainName() {
    throw new Error('getDomainName() must be implemented by subclass');
  }

  /**
   * Get domain slug for CSS classes and endpoints
   * 
   * @abstract
   * @returns {string} Domain slug
   */
  getDomainSlug() {
    throw new Error('getDomainSlug() must be implemented by subclass');
  }

  /**
   * Get WordPress post type for this domain
   * 
   * @abstract
   * @returns {string} Post type (used for AJAX actions)
   */
  getPostType() {
    throw new Error('getPostType() must be implemented by subclass');
  }

  /**
   * Get field names mapping
   * 
   * @abstract
   * @returns {Object} Field names object
   */
  getFieldNames() {
    throw new Error('getFieldNames() must be implemented by subclass');
  }

  /**
   * Get default values for form inputs
   * 
   * @abstract
   * @returns {Object} Default values object
   */
  getDefaultValues() {
    throw new Error('getDefaultValues() must be implemented by subclass');
  }

  /**
   * Get localized messages
   * 
   * @abstract
   * @returns {Object} Messages object
   */
  getMessages() {
    throw new Error('getMessages() must be implemented by subclass');
  }

}