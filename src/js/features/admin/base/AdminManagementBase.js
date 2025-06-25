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
    this.state.isInitialized = true;
  }

  /**
   * Set up event listeners for management interface
   * Uses event delegation for better performance
   */
  setupEventListeners() {
    // Quick action buttons (approve/reject)
    document.addEventListener('click', this.handleQuickAction);
    
    // Bulk actions
    document.addEventListener('click', this.handleBulkAction);
    
    // Save rejection reason
    document.addEventListener('click', this.handleSaveRejection);
    
    // Section toggle (collapsible sections)
    document.addEventListener('click', this.handleSectionToggle);
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
   * Handle quick action button clicks (approve/reject)
   * 
   * @param {Event} event - Click event
   */
  async handleQuickAction(event) {
    const button = event.target.closest('.action-approve, .action-reject');
    if (!button) return;

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

    // Get points value for approval
    let points = null;
    if (action === 'approve') {
      const pointsInput = document.querySelector(`.quick-points-input[data-post-id="${postId}"]`);
      points = pointsInput ? parseInt(pointsInput.value) || this.getDefaultValues().points : this.getDefaultValues().points;
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = messages.processing || 'Zpracovává se...';

    try {
      const response = await this.makeAjaxRequest(this.getQuickActionEndpoint(), {
        post_id: postId,
        [`${this.getDomainSlug()}_action`]: action,
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
    const button = event.target.closest('.bulk-approve-btn, .bulk-reject-btn');
    if (!button) return;

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
      const response = await this.makeAjaxRequest(this.getBulkActionEndpoint(), {
        user_id: userId,
        action: action,
        nonce: this.getNonce('bulk_approve')
      });

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

    const formData = new FormData();
    formData.append('action', action);
    
    Object.entries(data).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        formData.append(key, value);
      }
    });

    const response = await fetch(ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return await response.json();
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

  /**
   * Get quick action AJAX endpoint
   * 
   * @abstract
   * @returns {string} AJAX action name
   */
  getQuickActionEndpoint() {
    throw new Error('getQuickActionEndpoint() must be implemented by subclass');
  }

  /**
   * Get bulk action AJAX endpoint
   * 
   * @abstract
   * @returns {string} AJAX action name
   */
  getBulkActionEndpoint() {
    throw new Error('getBulkActionEndpoint() must be implemented by subclass');
  }
}