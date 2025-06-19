/**
 * Main JavaScript Entry Point
 * Imports and initializes all modules for the theme
 */

import { setupAresForm } from './features/ares/handler.js';
import { setupFileUpload } from './features/file-upload/index.js';
import { setupBusinessModal } from './features/admin/business-data-modal.js';
import { setupAccessControl } from './features/access-control/index.js';
import { app as firebaseApp } from './firebase/config.js';

/**
 * Theme Application Class
 * Manages initialization and lifecycle of all modules
 */
class ThemeApp {
  constructor() {
    this.modules = {
      aresHandler: null,
      fileUpload: null,
      businessModal: null,
      accessControl: null
    };
    this.firebase = firebaseApp;
    this.isInitialized = false;

    this.init();
  }

  /**
   * Initialize the application
   */
  init() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.setup());
    } else {
      this.setup();
    }
  }

  setup() {
    this.initializeModules();
    this.bindGlobalEvents();
    this.isInitialized = true;

    console.log('Theme application initialized successfully');
    console.log('Firebase app initialized:', this.firebase);
  }

  /**
   * Initialize all modules
   */
  initializeModules() {
    // Initialize access control first (highest priority for security)
    try {
      this.modules.accessControl = setupAccessControl();
      console.log('Access control initialized');
    } catch (error) {
      console.error('Failed to initialize access control:', error);
    }

    // Initialize ARES handler for company data lookup
    if (document.querySelector('.registration-form')) {
      try {
        this.modules.aresHandler = setupAresForm();
        console.log('ARES handler initialized');
      } catch (error) {
        console.error('Failed to initialize ARES handler:', error);
      }
    }

    // Initialize enhanced file upload for plugin (try multiple selectors)
    const fileUploadSelectors = [
      '#realizace-upload', 
      'input[data-name="mfile-747"]', 
      '.wpcf7-drag-n-drop-file', 
      '.codedropz-upload-wrapper'
    ];
    
    const fileUploadElement = fileUploadSelectors
      .map(sel => document.querySelector(sel))
      .find(el => el !== null);

    if (fileUploadElement) {
      try {
        this.modules.fileUpload = setupFileUpload(fileUploadElement);
        console.log('File upload handler initialized for plugin');
      } catch (error) {
        console.error('Failed to initialize file upload handler:', error);
      }
    } else {
      console.log('No file upload plugin elements found on page - will retry in 2 seconds');
      // Try again after a delay in case plugin loads dynamically
      setTimeout(() => {
        const delayedElement = fileUploadSelectors
          .map(sel => document.querySelector(sel))
          .find(el => el !== null);
          
        if (delayedElement) {
          try {
            this.modules.fileUpload = setupFileUpload(delayedElement);
            console.log('File upload handler initialized for plugin (delayed)');
          } catch (error) {
            console.error('Failed to initialize file upload handler (delayed):', error);
          }
        } else {
          console.log('No file upload plugin found after delay - plugin may not be active or form may not be on this page');
        }
      }, 2000);
    }

    // Initialize business data modal (admin pages only)
    if (window.location.pathname.includes('/wp-admin/')) {
      try {
        this.modules.businessModal = setupBusinessModal();
        console.log('Business data modal initialized for admin');
      } catch (error) {
        console.error('Failed to initialize business data modal:', error);
      }
    }
  }

  /**
   * Bind global event listeners
   */
  bindGlobalEvents() {
    // Handle Contact Form 7 submission success
    if (typeof window.wpcf7 !== 'undefined') {
      document.addEventListener('wpcf7mailsent', (event) => {
        this.handleFormSuccess(event);
      });

      document.addEventListener('wpcf7invalid', (event) => {
        this.handleFormError(event);
      });

      document.addEventListener('wpcf7spam', (event) => {
        this.handleFormSpam(event);
      });

      document.addEventListener('wpcf7mailfailed', (event) => {
        this.handleFormFailed(event);
      });
    }

    // Handle dynamic content loading (if needed for AJAX)
    document.addEventListener('DOMContentLoaded', () => {
      this.reinitializeOnDynamicContent();
    });
  }

  /**
   * Handle successful form submission
   * @param {Event} event - WPCF7 event
   */
  handleFormSuccess(event) {
    console.log('Form submitted successfully');

    // Reset file upload interface
    if (this.modules.fileUpload && this.modules.fileUpload.isReady()) {
      this.modules.fileUpload.onFormSuccess();
    }

    // Handle specific redirect for final registration form (ID 292)
    this.handleRegistrationFormRedirect(event);

    // You can add more success handling here
    // e.g., analytics tracking, user notifications, etc.
  }

  /**
   * Handle redirect for final registration form
   * @param {Event} event - WPCF7 event
   */
  handleRegistrationFormRedirect(event) {
    // Check if this is the final registration form (ID 292)
    const formElement = event.target;
    const formId = formElement ? formElement.querySelector('input[name="_wpcf7"]')?.value : null;
    
    // Get form ID from server-side configuration
    const finalRegistrationFormId = window.mistrFachman?.finalRegistrationFormId || '292';
    
    if (formId === finalRegistrationFormId) {
      console.log('Final registration form submitted successfully - redirecting to eshop');
      
      // Get eshop URL from server data or use fallback
      const eshopUrl = window.mistrFachman?.eshopUrl || '/obchod';
      
      setTimeout(() => {
        window.location.href = eshopUrl;
      }, 3000); // Wait for 3 seconds to allow user to see success message
    }
  }

  /**
   * Handle form validation errors
   * @param {Event} event - WPCF7 event
   */
  handleFormError(event) {
    console.log('Form validation failed');
    // Add custom error handling if needed
  }

  /**
   * Handle spam detection
   * @param {Event} event - WPCF7 event
   */
  handleFormSpam(event) {
    console.log('Form marked as spam');
    // Add custom spam handling if needed
  }

  /**
   * Handle mail sending failure
   * @param {Event} event - WPCF7 event
   */
  handleFormFailed(event) {
    console.log('Form mail sending failed');
    // Add custom failure handling if needed
  }

  /**
   * Reinitialize modules when content is dynamically loaded
   * Useful for AJAX-loaded content
   */
  reinitializeOnDynamicContent() {
    // Observer for dynamically added content
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
          // Check if any added nodes contain forms that need our modules
          const hasRegistrationForm = Array.from(mutation.addedNodes).some(node => {
            return node.nodeType === Node.ELEMENT_NODE &&
                   (node.matches('.registration-form') || node.querySelector('.registration-form'));
          });

          if (hasRegistrationForm) {
            console.log('Dynamic content detected, reinitializing modules');
            this.reinitializeModules();
          }
        }
      });
    });

    // Start observing
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  /**
   * Reinitialize modules (useful for dynamic content)
   */
  reinitializeModules() {
    // Clean up existing modules
    this.destroyModules();

    // Reinitialize
    this.initializeModules();
  }

  /**
   * Destroy all modules
   */
  destroyModules() {
    Object.keys(this.modules).forEach(key => {
      if (this.modules[key] && typeof this.modules[key].cleanup === 'function') {
        this.modules[key].cleanup();
      } else if (this.modules[key] && typeof this.modules[key].destroy === 'function') {
        this.modules[key].destroy();
      }
      this.modules[key] = null;
    });

    // No longer need to clear singleton instances with functional modules
  }

  /**
   * Get module instance
   * @param {string} moduleName - Name of the module
   * @returns {Object|null} Module instance
   */
  getModule(moduleName) {
    return this.modules[moduleName] || null;
  }

  /**
   * Check if application is ready
   * @returns {boolean}
   */
  isReady() {
    return this.isInitialized;
  }

  /**
   * Destroy the application
   */
  destroy() {
    this.destroyModules();
    this.isInitialized = false;
    console.log('Theme application destroyed');
  }
}

// Initialize the application
const app = new ThemeApp();

// Make app available globally for debugging and external access
window.ThemeApp = app;

// For development debugging
if (process.env.NODE_ENV === 'development') {
  window.ThemeAppDebug = {
    app,
    modules: app.modules,
    // Add other utilities for debugging
  };
}
