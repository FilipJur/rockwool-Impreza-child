/**
 * Business Data Modal - Modern ES6+ Implementation
 * 
 * Handles business data modal interactions using vanilla JavaScript.
 * No jQuery dependency - uses modern DOM APIs and fetch.
 */

// Note: Using direct fetch instead of fetchWithNonce to handle fresh nonce generation

class BusinessDataModal {
  constructor() {
    console.log('BusinessDataModal: Constructor called');
    this.modal = null;
    this.isInitialized = false;
    this.init();
  }

  /**
   * Initialize the modal functionality
   */
  init() {
    console.log('BusinessDataModal: init() called, readyState:', document.readyState);
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.setupEventListeners());
    } else {
      this.setupEventListeners();
    }
  }

  /**
   * Setup event listeners for modal interactions
   */
  setupEventListeners() {
    console.log('BusinessDataModal: setupEventListeners() called');
    
    // Check for existing buttons
    const reviewButtons = document.querySelectorAll('.review-business-data-btn');
    console.log('BusinessDataModal: Found', reviewButtons.length, 'review buttons on page');
    
    // Handle business data review button clicks
    document.addEventListener('click', (e) => {
      console.log('BusinessDataModal: Document click detected, target:', e.target);
      // Check for the button or its children (icon/text)
      const button = e.target.closest('.review-business-data-btn');
      if (button) {
        console.log('BusinessDataModal: Review button clicked, userId:', button.dataset.userId);
        e.preventDefault();
        this.handleReviewButtonClick(button);
      }
    });

    // Handle modal close buttons
    document.addEventListener('click', (e) => {
      if (e.target.matches('.mistr-modal-close') || e.target.matches('.mistr-modal-backdrop')) {
        console.log('BusinessDataModal: Close button clicked');
        this.closeModal();
      }
    });

    // Handle Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.isModalOpen()) {
        console.log('BusinessDataModal: Escape key pressed, closing modal');
        this.closeModal();
      }
    });

    this.isInitialized = true;
    console.log('BusinessDataModal: Event listeners setup complete');
  }

  /**
   * Handle review button click
   * @param {HTMLElement} button - The clicked button
   */
  async handleReviewButtonClick(button) {
    const userId = button.dataset.userId;
    
    if (!userId) {
      console.error('User ID not found on review button');
      return;
    }

    // Find and show modal
    this.modal = document.getElementById('business-data-modal');
    if (!this.modal) {
      console.error('Business data modal not found');
      return;
    }

    this.showModal();
    this.showLoadingState();

    try {
      const businessData = await this.fetchBusinessData(userId);
      this.showBusinessData(businessData);
    } catch (error) {
      console.error('Failed to load business data:', error);
      this.showError(error.message || 'Chyba při načítání údajů');
    }
  }

  /**
   * Get fresh nonce for AJAX requests
   * @param {string} action - Action name
   * @returns {Promise<string>} Fresh nonce
   */
  async getFreshNonce(action = 'mistr_fachman_business_data') {
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
  async fetchBusinessData(userId) {
    console.log('BusinessDataModal: fetchBusinessData called for user:', userId);
    
    // Get fresh nonce to avoid timing issues
    const freshNonce = await this.getFreshNonce('mistr_fachman_business_data');
    
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
  showModal() {
    if (!this.modal) return;
    
    this.modal.classList.add('is-open');
    this.modal.style.display = 'block';
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
    
    // Focus trap for accessibility
    this.trapFocus();
  }

  /**
   * Close the modal
   */
  closeModal() {
    if (!this.modal) return;
    
    this.modal.classList.remove('is-open');
    this.modal.style.display = 'none';
    
    // Restore body scroll
    document.body.style.overflow = '';
    
    // Clear modal content
    this.clearModalContent();
  }

  /**
   * Show loading state
   */
  showLoadingState() {
    const loadingDiv = this.modal?.querySelector('.mistr-modal-loading');
    const dataDiv = this.modal?.querySelector('.mistr-modal-data');
    
    if (loadingDiv) loadingDiv.style.display = 'block';
    if (dataDiv) dataDiv.style.display = 'none';
  }

  /**
   * Show business data
   * @param {string} html - HTML content to display
   */
  showBusinessData(html) {
    const loadingDiv = this.modal?.querySelector('.mistr-modal-loading');
    const dataDiv = this.modal?.querySelector('.mistr-modal-data');
    
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
  showError(message) {
    const loadingDiv = this.modal?.querySelector('.mistr-modal-loading');
    const dataDiv = this.modal?.querySelector('.mistr-modal-data');
    
    if (loadingDiv) loadingDiv.style.display = 'none';
    if (dataDiv) {
      dataDiv.innerHTML = `<p class="error">Chyba při načítání údajů: ${message}</p>`;
      dataDiv.style.display = 'block';
    }
  }

  /**
   * Clear modal content
   */
  clearModalContent() {
    const dataDiv = this.modal?.querySelector('.mistr-modal-data');
    if (dataDiv) {
      dataDiv.innerHTML = '';
      dataDiv.style.display = 'none';
    }
  }

  /**
   * Check if modal is open
   * @returns {boolean}
   */
  isModalOpen() {
    return this.modal && this.modal.classList.contains('is-open');
  }

  /**
   * Trap focus within modal for accessibility
   */
  trapFocus() {
    if (!this.modal) return;

    const focusableElements = this.modal.querySelectorAll(
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
    this.modal.addEventListener('keydown', handleTabKey);
    
    // Remove tab handler when modal closes
    const cleanup = () => {
      this.modal?.removeEventListener('keydown', handleTabKey);
    };
    
    // Store cleanup function for later use
    this.modal._focusTrapCleanup = cleanup;
  }
}

// Export the class for initialization by main.js
export default BusinessDataModal;