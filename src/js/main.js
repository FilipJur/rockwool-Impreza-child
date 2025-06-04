/**
 * Main JavaScript Entry Point
 * Imports and initializes all modules for the theme
 */

import { AresHandler } from './modules/ares-handler.js';
import { FileUpload } from './modules/file-upload.js';

/**
 * Theme Application Class
 * Manages initialization and lifecycle of all modules
 */
class ThemeApp {
  constructor() {
    this.modules = {
      aresHandler: null,
      fileUpload: null
    };
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
  }

  /**
   * Initialize all modules
   */
  initializeModules() {
    // Initialize ARES handler for company data lookup
    if (document.querySelector('.registration-form')) {
      try {
        this.modules.aresHandler = new AresHandler();
        console.log('ARES handler initialized');
      } catch (error) {
        console.error('Failed to initialize ARES handler:', error);
      }
    }

    // Initialize enhanced file upload
    if (document.querySelector('.registration-form input[name="fotky-realizace"]')) {
      try {
        this.modules.fileUpload = new FileUpload();
        console.log('File upload handler initialized');
      } catch (error) {
        console.error('Failed to initialize file upload handler:', error);
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

    // You can add more success handling here
    // e.g., analytics tracking, user notifications, etc.
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
      if (this.modules[key] && typeof this.modules[key].destroy === 'function') {
        this.modules[key].destroy();
      }
      this.modules[key] = null;
    });

    // Clear singleton instances
    FileUpload.clearInstance();
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
