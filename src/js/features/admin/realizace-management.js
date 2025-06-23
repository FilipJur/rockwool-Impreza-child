/**
 * Realizace Management Admin Module
 *
 * Handles quick actions (approve/reject) and bulk operations for realizace management
 * in the WordPress admin user management interface.
 */

/**
 * Setup realizace management functionality
 * @param {string} containerSelector - Container selector for realizace cards
 * @returns {Object} Module instance with cleanup function
 */
export function setupRealizaceManagement(containerSelector = '.realizace-management-modern') {
  console.log('Setting up realizace management...');

  let isInitialized = false;
  const state = {
    containers: [],
    activeRequests: new Set()
  };

  // Prevent double initialization and inline script conflicts
  if (window.realizaceManagementInitialized) {
    console.warn('Realizace management already initialized elsewhere');
    return { cleanup: () => {}, isInitialized: () => false };
  }
  window.realizaceManagementInitialized = true;

  /**
   * Show notification message
   * @param {string} message - Message to display
   * @param {string} type - Type of notification (success, error, info)
   */
  function showNotification(message, type = 'info') {
    // Remove existing notifications
    document.querySelectorAll('.realizace-notification').forEach(el => el.remove());

    const notification = document.createElement('div');
    notification.className = `realizace-notification notice notice-${type} is-dismissible`;
    notification.innerHTML = `
      <p>${message}</p>
      <button type="button" class="notice-dismiss">
        <span class="screen-reader-text">Dismiss this notice.</span>
      </button>
    `;

    // Insert at the top of the admin content
    const adminContent = document.querySelector('.wrap') || document.querySelector('#wpbody-content');
    if (adminContent) {
      adminContent.insertBefore(notification, adminContent.firstChild);
    }

    // Auto dismiss after 5 seconds
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 5000);

    // Handle manual dismiss
    const dismissBtn = notification.querySelector('.notice-dismiss');
    if (dismissBtn) {
      dismissBtn.addEventListener('click', () => notification.remove());
    }
  }

  /**
   * Show confirmation dialog
   * @param {string} message - Confirmation message
   * @returns {boolean} True if confirmed
   */
  function showConfirmation(message) {
    return confirm(message);
  }

  /**
   * Initialize the module
   */
  function init() {
    if (isInitialized) {
      console.warn('Realizace management already initialized');
      return;
    }

    const containers = document.querySelectorAll(containerSelector);
    if (containers.length === 0) {
      console.log('No realizace management containers found');
      return;
    }

    state.containers = Array.from(containers);
    bindEvents();

    isInitialized = true;
    console.log(`Realizace management initialized for ${containers.length} container(s)`);
  }

  /**
   * Bind event handlers
   */
  function bindEvents() {
    // Use event delegation for dynamic content
    document.addEventListener('click', handleQuickAction);
    document.addEventListener('click', handleBulkApprove);
    document.addEventListener('click', handleSaveRejection);
  }

  /**
   * Handle quick approve/reject actions
   * @param {Event} event - Click event
   */
  function handleQuickAction(event) {
    const button = event.target.closest('.action-approve, .action-reject');
    if (!button || !isRealizaceContainer(event.target)) {
      return;
    }

    event.preventDefault();

    const postId = button.dataset.postId;
    const action = button.dataset.action;

    if (!postId || !action) {
      console.error('Missing required data attributes on action button');
      return;
    }

    // Prevent multiple requests for the same post
    const requestId = `${action}-${postId}`;
    if (state.activeRequests.has(requestId)) {
      return;
    }

    performQuickAction(button, postId, action, requestId);
  }

  /**
   * Handle save rejection reason
   * @param {Event} event - Click event
   */
  function handleSaveRejection(event) {
    const button = event.target.closest('.save-rejection-btn');
    if (!button || !isRealizaceContainer(event.target)) {
      return;
    }

    event.preventDefault();

    const postId = button.dataset.postId;
    if (!postId) {
      console.error('Missing post ID on save rejection button');
      return;
    }

    // Prevent multiple requests for the same post
    const requestId = `save-rejection-${postId}`;
    if (state.activeRequests.has(requestId)) {
      return;
    }

    performSaveRejection(button, postId, requestId);
  }

  /**
   * Handle bulk approve action
   * @param {Event} event - Click event
   */
  function handleBulkApprove(event) {
    const button = event.target.closest('.bulk-approve-btn');
    if (!button || !isRealizaceContainer(event.target)) {
      return;
    }

    event.preventDefault();

    const userId = button.dataset.userId;
    if (!userId) {
      console.error('Missing user ID on bulk approve button');
      return;
    }

    // Prevent multiple requests
    const requestId = `bulk-approve-${userId}`;
    if (state.activeRequests.has(requestId)) {
      return;
    }

    performBulkApprove(button, userId, requestId);
  }

  /**
   * Check if target is within a realizace container
   * @param {Element} target - Event target
   * @returns {boolean} True if within realizace container
   */
  function isRealizaceContainer(target) {
    return state.containers.some(container => container.contains(target));
  }

  /**
   * Perform quick action (approve/reject)
   * @param {Element} button - Action button
   * @param {string} postId - Post ID
   * @param {string} action - Action type (approve/reject)
   * @param {string} requestId - Unique request identifier
   */
  async function performQuickAction(button, postId, action, requestId) {
    // Determine if this is rejecting an approved post
    const realizaceItem = button.closest('.realizace-item');
    const statusBadge = realizaceItem ? realizaceItem.querySelector('.status-badge.status-publish') : null;
    const isApprovedRevoke = statusBadge && action === 'reject';
    
    const actionText = action === 'approve' ? 'schválit' : (isApprovedRevoke ? 'zrušit schválení' : 'odmítnout');
    
    // For approve action, get points value and validate
    let points = null;
    let rejectionReason = null;
    
    if (action === 'approve') {
      const pointsInput = document.querySelector('.quick-points-input[data-post-id="' + postId + '"]');
      points = pointsInput ? parseInt(pointsInput.value, 10) : 0;
      
      if (!points || points <= 0) {
        showNotification('Zadejte platný počet bodů.', 'error');
        return;
      }
    } else if (action === 'reject') {
      // For rejecting approved posts, require rejection reason
      if (isApprovedRevoke) {
        rejectionReason = prompt('Zadejte důvod zrušení schválení:');
        if (!rejectionReason || !rejectionReason.trim()) {
          showNotification('Důvod zrušení schválení je povinný.', 'error');
          return;
        }
        rejectionReason = rejectionReason.trim();
      } else {
        // For pending posts, get from textarea
        const rejectionInput = document.querySelector('.rejection-reason-input[data-post-id="' + postId + '"]');
        rejectionReason = rejectionInput ? rejectionInput.value.trim() : '';
        
        if (!rejectionReason) {
          showNotification('Zadejte důvod zamítnutí.', 'error');
          return;
        }
      }
    }
    
    const confirmMessage = isApprovedRevoke 
      ? `Opravdu chcete zrušit schválení této realizace? Bude přesunuta do stavu "Odmítnuto".`
      : `Opravdu chcete ${actionText} tuto realizaci?`;
    
    if (!showConfirmation(confirmMessage)) {
      return;
    }

    // Get localized data
    const ajaxData = window.mistrRealizaceAdmin || {};
    const nonce = ajaxData.nonces?.quick_action || '';

    if (!nonce) {
      console.error('Missing AJAX nonce for quick action');
      showNotification('Chyba: Chybí bezpečnostní token. Obnovte stránku a zkuste znovu.', 'error');
      return;
    }

    // Update button state
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Zpracovává se...';

    state.activeRequests.add(requestId);

    try {
      const requestData = {
        post_id: postId,
        realizace_action: action,
        nonce: nonce
      };
      
      // Add points for approve action
      if (action === 'approve' && points) {
        requestData.points = points;
      }
      
      // Add rejection reason for reject action
      if (action === 'reject' && rejectionReason) {
        requestData.rejection_reason = rejectionReason;
      }
      
      const response = await makeAjaxRequest('mistr_fachman_realizace_quick_action', requestData);

      if (response.success) {
        showNotification(response.data.message, 'success');
        // Reload the page to show updated status
        setTimeout(() => window.location.reload(), 1500);
      } else {
        throw new Error(response.data?.message || 'Neznámá chyba');
      }
    } catch (error) {
      console.error('Quick action failed:', error);
      showNotification(`Chyba při ${actionText === 'schválit' ? 'schvalování' : 'odmítání'}: ${error.message}`, 'error');

      // Restore button state
      button.disabled = false;
      button.textContent = originalText;
    } finally {
      state.activeRequests.delete(requestId);
    }
  }

  /**
   * Perform save rejection reason (updates ACF field)
   * @param {Element} button - Save rejection button
   * @param {string} postId - Post ID
   * @param {string} requestId - Unique request identifier
   */
  async function performSaveRejection(button, postId, requestId) {
    const rejectionInput = document.querySelector('.rejection-reason-input[data-post-id="' + postId + '"]');
    const rejectionReason = rejectionInput ? rejectionInput.value.trim() : '';
    
    if (!rejectionReason) {
      showNotification('Zadejte důvod odmítnutí.', 'error');
      return;
    }

    // Get localized data
    const ajaxData = window.mistrRealizaceAdmin || {};
    const nonce = ajaxData.nonces?.save_rejection || ajaxData.nonces?.quick_action || '';

    if (!nonce) {
      console.error('Missing AJAX nonce for save rejection');
      showNotification('Chyba: Chybí bezpečnostní token. Obnovte stránku a zkuste znovu.', 'error');
      return;
    }

    // Update button state
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Ukládá se...';

    state.activeRequests.add(requestId);

    try {
      // Update ACF field directly
      const response = await makeAjaxRequest('mistr_fachman_update_acf_field', {
        post_id: postId,
        field_name: 'duvod_zamitnuti',
        field_value: rejectionReason,
        nonce: nonce
      });

      if (response.success) {
        showNotification('Důvod odmítnutí byl uložen do ACF pole.', 'success');
        button.textContent = 'Uloženo ✓';
        setTimeout(() => {
          button.textContent = originalText;
          button.disabled = false;
        }, 2000);
      } else {
        throw new Error(response.data?.message || 'Neznámá chyba');
      }
    } catch (error) {
      console.error('Save rejection to ACF failed:', error);
      showNotification(`Chyba při ukládání do ACF: ${error.message}`, 'error');

      // Restore button state
      button.disabled = false;
      button.textContent = originalText;
    } finally {
      state.activeRequests.delete(requestId);
    }
  }

  /**
   * Perform bulk approve action
   * @param {Element} button - Bulk approve button
   * @param {string} userId - User ID
   * @param {string} requestId - Unique request identifier
   */
  async function performBulkApprove(button, userId, requestId) {
    const confirmMessage = 'Opravdu chcete hromadně schválit všechny čekající realizace tohoto uživatele?';

    if (!showConfirmation(confirmMessage)) {
      return;
    }

    // Get localized data
    const ajaxData = window.mistrRealizaceAdmin || {};
    const nonce = ajaxData.nonces?.bulk_approve || '';

    if (!nonce) {
      console.error('Missing AJAX nonce for bulk approve');
      showNotification('Chyba: Chybí bezpečnostní token. Obnovte stránku a zkuste znovu.', 'error');
      return;
    }

    // Update button state
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Zpracovává se...';

    state.activeRequests.add(requestId);

    // Collect individual points for each pending realizace
    const pointsData = {};
    const pointsInputs = document.querySelectorAll('.quick-points-input[data-post-id]');
    pointsInputs.forEach(input => {
      const postId = input.dataset.postId;
      const points = parseInt(input.value, 10) || 0;
      if (points > 0) {
        pointsData[postId] = points;
      }
    });

    try {
      const response = await makeAjaxRequest('mistr_fachman_bulk_approve_realizace', {
        user_id: userId,
        points_data: JSON.stringify(pointsData),
        nonce: nonce
      });

      if (response.success) {
        showNotification(response.data.message, 'success');
        // Reload the page to show updated status
        setTimeout(() => window.location.reload(), 1500);
      } else {
        throw new Error(response.data?.message || 'Neznámá chyba');
      }
    } catch (error) {
      console.error('Bulk approve failed:', error);
      showNotification(`Chyba při hromadném schvalování: ${error.message}`, 'error');

      // Restore button state
      button.disabled = false;
      button.textContent = originalText;
    } finally {
      state.activeRequests.delete(requestId);
    }
  }

  /**
   * Make AJAX request to WordPress
   * @param {string} action - WordPress AJAX action
   * @param {Object} data - Request data
   * @returns {Promise<Object>} Response data
   */
  async function makeAjaxRequest(action, data) {
    const ajaxData = window.mistrRealizaceAdmin || {};
    const ajaxUrl = ajaxData.ajax_url || window.ajaxurl || '/wp-admin/admin-ajax.php';

    const formData = new FormData();
    formData.append('action', action);

    // Add all data parameters
    Object.entries(data).forEach(([key, value]) => {
      formData.append(key, value);
    });

    const response = await fetch(ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const result = await response.json();
    return result;
  }

  /**
   * Cleanup function
   */
  function cleanup() {
    if (!isInitialized) {
      return;
    }

    // Remove event listeners
    document.removeEventListener('click', handleQuickAction);
    document.removeEventListener('click', handleBulkApprove);
    document.removeEventListener('click', handleSaveRejection);

    // Clear state
    state.containers = [];
    state.activeRequests.clear();

    // Remove notifications
    document.querySelectorAll('.realizace-notification').forEach(el => el.remove());

    // Reset initialization flag
    window.realizaceManagementInitialized = false;

    isInitialized = false;
    console.log('Realizace management cleanup completed');
  }

  // Initialize immediately
  init();

  // Return public interface
  return {
    cleanup,
    isInitialized: () => isInitialized,
    getState: () => ({ ...state }),
    reinitialize: () => {
      cleanup();
      init();
    }
  };
}
