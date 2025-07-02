/**
 * Žebříček (Leaderboard) Feature Module
 * Handles pagination and dynamic loading for leaderboard shortcodes
 */

/**
 * Setup function for zebricek features
 * @returns {Object} Module instance with cleanup method
 */
export function setupZebricek() {
  const module = {
    isReady: false,
    
    /**
     * Initialize the module
     */
    init() {
      this.bindPaginationEvents();
      this.isReady = true;
      console.log('Žebříček module initialized');
    },

    /**
     * Bind pagination events for "Více" buttons
     */
    bindPaginationEvents() {
      // Find all zebricek containers
      const zebricekContainers = document.querySelectorAll('.mycred-zebricek-leaderboard');
      
      zebricekContainers.forEach(container => {
        this.initializePagination(container);
      });
    },

    /**
     * Initialize pagination for a specific container
     * @param {HTMLElement} container - Zebricek container element
     */
    initializePagination(container) {
      const moreButton = container.querySelector('.zebricek-more-btn');
      
      if (!moreButton) {
        return;
      }

      this.createPaginationHandler(container, moreButton);
    },

    /**
     * Create pagination handler for a button
     * @param {HTMLElement} container - Container element
     * @param {HTMLElement} button - More button element
     * @returns {Function} Event handler function
     */
    createPaginationHandler(container, button) {
      const handler = async (event) => {
        event.preventDefault();
        
        const offset = parseInt(button.dataset.offset) || 0;
        const limit = parseInt(button.dataset.limit) || 30;
        
        await this.loadMoreUsers(container, button, offset, limit);
      };

      button.addEventListener('click', handler);
    },

    /**
     * Load more users via AJAX
     * @param {HTMLElement} container - Container element
     * @param {HTMLElement} button - More button element
     * @param {number} offset - Current offset
     * @param {number} limit - Items per page
     */
    async loadMoreUsers(container, button, offset, limit) {
      // Show loading state
      this.setLoadingState(button, true);
      
      try {
        // Get current shortcode attributes from container
        const shortcodeData = this.extractShortcodeData(container);
        
        // Prepare AJAX request
        const response = await fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'zebricek_load_more',
            nonce: window.zebricekAjax?.nonce || '',
            offset: offset,
            limit: limit,
            ...shortcodeData
          })
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        if (data.success) {
          // Append new users to the list
          this.appendUsers(container, data.data.html);
          
          // Update or hide pagination button
          if (data.data.has_more) {
            button.dataset.offset = offset + limit;
          } else {
            button.style.display = 'none';
          }
        } else {
          throw new Error(data.data?.message || 'Failed to load more users');
        }
        
      } catch (error) {
        console.error('Error loading more users:', error);
        this.showError(container, 'Nepodařilo se načíst další uživatele. Zkuste to prosím znovu.');
      } finally {
        this.setLoadingState(button, false);
      }
    },

    /**
     * Extract shortcode configuration from container
     * @param {HTMLElement} container - Container element
     * @returns {Object} Shortcode configuration
     */
    extractShortcodeData(container) {
      return {
        show_numbers: container.dataset.showNumbers || 'true',
        show_pagination: container.dataset.showPagination || 'true',
        class: container.dataset.class || ''
      };
    },

    /**
     * Append new users to the leaderboard list
     * @param {HTMLElement} container - Container element
     * @param {string} html - HTML content to append
     */
    appendUsers(container, html) {
      const zebricekList = container.querySelector('.zebricek-list');
      
      if (zebricekList && html) {
        // Create temporary container to parse HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        // Append each new row
        const newRows = tempDiv.querySelectorAll('.zebricek-row');
        newRows.forEach(row => {
          zebricekList.appendChild(row);
        });
        
        // Add animation for new rows
        this.animateNewRows(newRows);
      }
    },

    /**
     * Animate newly added rows
     * @param {NodeList} rows - New row elements
     */
    animateNewRows(rows) {
      rows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
          row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
          row.style.opacity = '1';
          row.style.transform = 'translateY(0)';
        }, index * 100); // Stagger animation
      });
    },

    /**
     * Set loading state for button
     * @param {HTMLElement} button - Button element
     * @param {boolean} loading - Loading state
     */
    setLoadingState(button, loading) {
      if (loading) {
        button.disabled = true;
        button.textContent = 'Načítání...';
        button.classList.add('zebricek-more-btn--loading');
      } else {
        button.disabled = false;
        button.textContent = 'Více';
        button.classList.remove('zebricek-more-btn--loading');
      }
    },

    /**
     * Show error message in container
     * @param {HTMLElement} container - Container element
     * @param {string} message - Error message
     */
    showError(container, message) {
      // Remove existing error messages
      const existingErrors = container.querySelectorAll('.zebricek-error');
      existingErrors.forEach(error => error.remove());
      
      // Create error element
      const errorDiv = document.createElement('div');
      errorDiv.className = 'zebricek-error';
      errorDiv.textContent = message;
      
      // Insert before pagination
      const pagination = container.querySelector('.zebricek-pagination');
      if (pagination) {
        pagination.parentNode.insertBefore(errorDiv, pagination);
      } else {
        container.appendChild(errorDiv);
      }
      
      // Auto-remove error after 5 seconds
      setTimeout(() => {
        errorDiv.remove();
      }, 5000);
    },

    /**
     * Reinitialize for dynamically loaded content
     */
    reinitialize() {
      this.cleanup();
      this.init();
    },

    /**
     * Cleanup event listeners
     */
    cleanup() {
      // Event listeners will be cleaned up when elements are removed
      this.isReady = false;
    },

    /**
     * Check if module is ready
     * @returns {boolean}
     */
    isReady() {
      return this.isReady;
    }
  };

  // Initialize immediately
  module.init();
  
  return module;
}