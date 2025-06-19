/**
 * Business Data Modal - Functional Implementation
 * 
 * Handles business data modal interactions using vanilla JavaScript.
 * No jQuery dependency - uses modern DOM APIs and fetch.
 */

/**
 * Setup business data modal functionality
 * @param {string|HTMLElement} modalSelector - CSS selector or element for modal
 * @param {Object} options - Configuration options
 * @returns {Object|null} Handler object with methods, or null if setup fails
 */
export function setupBusinessModal(modalSelector = '#business-data-modal', options = {}) {
  // Find the modal element
  let modal;
  if (typeof modalSelector === 'string') {
    modal = document.querySelector(modalSelector);
  } else if (modalSelector instanceof HTMLElement) {
    modal = modalSelector;
  } else {
    console.warn('[BusinessModal] Invalid modal selector provided');
    return null;
  }

  // Modal is optional - it might be injected dynamically
  if (!modal) {
    console.log(`[BusinessModal] Modal "${modalSelector}" not found initially - will search dynamically`);
  }

  // State variables
  let isInitialized = false;
  const eventListeners = [];

  /**
   * Initialize the modal functionality
   */
  function init() {
    console.log('BusinessDataModal: init() called, readyState:', document.readyState);
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', setupEventListeners);
    } else {
      setupEventListeners();
    }
  }

  /**
   * Setup event listeners for modal interactions
   */
  function setupEventListeners() {
    console.log('BusinessDataModal: setupEventListeners() called');
    
    // Check for existing buttons
    const reviewButtons = document.querySelectorAll('.review-business-data-btn');
    console.log('BusinessDataModal: Found', reviewButtons.length, 'review buttons on page');
    
    // Event handlers
    const handleDocumentClick = (e) => {
      console.log('BusinessDataModal: Document click detected, target:', e.target);
      // Check for the button or its children (icon/text)
      const button = e.target.closest('.review-business-data-btn');
      if (button) {
        console.log('BusinessDataModal: Review button clicked, userId:', button.dataset.userId);
        e.preventDefault();
        handleReviewButtonClick(button);
      }
    };

    const handleModalClose = (e) => {
      if (e.target.matches('.mistr-modal-close') || e.target.matches('.mistr-modal-backdrop')) {
        console.log('BusinessDataModal: Close button clicked');
        closeModal();
      }
    };

    const handleEscapeKey = (e) => {
      if (e.key === 'Escape' && isModalOpen()) {
        console.log('BusinessDataModal: Escape key pressed, closing modal');
        closeModal();
      }
    };

    // Bind events and track for cleanup
    document.addEventListener('click', handleDocumentClick);
    eventListeners.push({ element: document, event: 'click', handler: handleDocumentClick });

    document.addEventListener('click', handleModalClose);
    eventListeners.push({ element: document, event: 'click', handler: handleModalClose });

    document.addEventListener('keydown', handleEscapeKey);
    eventListeners.push({ element: document, event: 'keydown', handler: handleEscapeKey });

    isInitialized = true;
    console.log('BusinessDataModal: Event listeners setup complete');
  }

  /**
   * Handle review button click
   * @param {HTMLElement} button - The clicked button
   */
  async function handleReviewButtonClick(button) {
    const userId = button.dataset.userId;
    
    if (!userId) {
      console.error('User ID not found on review button');
      return;
    }

    // Find modal if not already found
    if (!modal) {
      modal = document.getElementById('business-data-modal');
    }
    
    if (!modal) {
      console.error('Business data modal not found');
      return;
    }

    showModal();
    showLoadingState();

    try {
      const businessData = await fetchBusinessData(userId);
      showBusinessData(businessData);
    } catch (error) {
      console.error('Failed to load business data:', error);
      showError(error.message || 'Chyba při načítání údajů');
    }
  }

  /**
   * Get fresh nonce for AJAX requests
   * @param {string} action - Action name
   * @returns {Promise<string>} Fresh nonce
   */
  async function getFreshNonce(action = 'mistr_fachman_business_data') {
    const formData = new FormData();
    formData.append('action', 'mistr_fachman_get_fresh_nonce');
    formData.append('action_name', action);

    try {
      const response = await fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });

      const data = await response.json();
      
      if (data.success) {
        console.log('BusinessDataModal: Fresh nonce obtained:', data.data.nonce);
        return data.data.nonce;
      } else {
        throw new Error('Failed to get fresh nonce');
      }
    } catch (error) {
      console.warn('BusinessDataModal: Could not get fresh nonce, using cached:', error);
      return window.mistrFachmanAjax?.nonce || '';
    }
  }

  /**
   * Fetch business data via AJAX
   * @param {string} userId - User ID
   * @returns {Promise<string>} HTML content
   */
  async function fetchBusinessData(userId) {
    console.log('BusinessDataModal: fetchBusinessData called for user:', userId);
    
    // Get fresh nonce to avoid timing issues
    const freshNonce = await getFreshNonce('mistr_fachman_business_data');
    
    const formData = new FormData();
    formData.append('action', 'mistr_fachman_get_business_data');
    formData.append('user_id', userId);
    formData.append('nonce', freshNonce);

    console.log('BusinessDataModal: Using fresh nonce:', freshNonce);

    try {
      const response = await fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });

      const data = await response.json();
      console.log('BusinessDataModal: AJAX response:', data);

      if (!data.success) {
        throw new Error(data.data?.message || 'Neznámá chyba');
      }

      return data.data.html;
    } catch (error) {
      console.error('BusinessDataModal: AJAX error:', error);
      throw error;
    }
  }

  /**
   * Show the modal
   */
  function showModal() {
    if (!modal) return;
    
    modal.classList.add('is-open');
    modal.style.display = 'block';
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
    
    // Focus trap for accessibility
    trapFocus();
  }

  /**
   * Close the modal
   */
  function closeModal() {
    if (!modal) return;
    
    modal.classList.remove('is-open');
    modal.style.display = 'none';
    
    // Restore body scroll
    document.body.style.overflow = '';
    
    // Clear modal content
    clearModalContent();
  }

  /**
   * Show loading state
   */
  function showLoadingState() {
    const loadingDiv = modal?.querySelector('.mistr-modal-loading');
    const dataDiv = modal?.querySelector('.mistr-modal-data');
    
    if (loadingDiv) loadingDiv.style.display = 'block';
    if (dataDiv) dataDiv.style.display = 'none';
  }

  /**
   * Show business data
   * @param {string} html - HTML content to display
   */
  function showBusinessData(html) {
    const loadingDiv = modal?.querySelector('.mistr-modal-loading');
    const dataDiv = modal?.querySelector('.mistr-modal-data');
    
    if (loadingDiv) loadingDiv.style.display = 'none';
    if (dataDiv) {
      dataDiv.innerHTML = html;
      dataDiv.style.display = 'block';
    }
  }

  /**
   * Show error message
   * @param {string} message - Error message
   */
  function showError(message) {
    const loadingDiv = modal?.querySelector('.mistr-modal-loading');
    const dataDiv = modal?.querySelector('.mistr-modal-data');
    
    if (loadingDiv) loadingDiv.style.display = 'none';
    if (dataDiv) {
      dataDiv.innerHTML = `<p class="error">Chyba při načítání údajů: ${message}</p>`;
      dataDiv.style.display = 'block';
    }
  }

  /**
   * Clear modal content
   */
  function clearModalContent() {
    const dataDiv = modal?.querySelector('.mistr-modal-data');
    if (dataDiv) {
      dataDiv.innerHTML = '';
      dataDiv.style.display = 'none';
    }
  }

  /**
   * Check if modal is open
   * @returns {boolean}
   */
  function isModalOpen() {
    return modal && modal.classList.contains('is-open');
  }

  /**
   * Trap focus within modal for accessibility
   */
  function trapFocus() {
    if (!modal) return;

    const focusableElements = modal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    
    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];

    // Focus first element
    if (firstFocusable) {
      firstFocusable.focus();
    }

    // Handle tab navigation
    const handleTabKey = (e) => {
      if (e.key !== 'Tab') return;

      if (e.shiftKey) {
        if (document.activeElement === firstFocusable) {
          e.preventDefault();
          lastFocusable?.focus();
        }
      } else {
        if (document.activeElement === lastFocusable) {
          e.preventDefault();
          firstFocusable?.focus();
        }
      }
    };

    // Add tab handler when modal is open
    modal.addEventListener('keydown', handleTabKey);
    
    // Store cleanup for focus trap
    modal._focusTrapHandler = handleTabKey;
  }

  /**
   * Cleanup function
   */
  function cleanup() {
    // Remove event listeners
    eventListeners.forEach(({ element, event, handler }) => {
      element.removeEventListener(event, handler);
    });
    eventListeners.length = 0;

    // Cleanup focus trap
    if (modal && modal._focusTrapHandler) {
      modal.removeEventListener('keydown', modal._focusTrapHandler);
      delete modal._focusTrapHandler;
    }

    // Close modal if open
    if (isModalOpen()) {
      closeModal();
    }

    isInitialized = false;
    console.log('[BusinessModal] Cleanup completed');
  }

  // Initialize the handler
  init();

  // Return handler object with public methods
  return {
    cleanup,
    isReady: () => isInitialized,
    showModal,
    closeModal,
    showLoadingState,
    showBusinessData,
    showError,
    clearModalContent,
    isModalOpen,
    getModal: () => modal,
    fetchBusinessData,
    getFreshNonce
  };
}

// Export the setup function
export default setupBusinessModal;