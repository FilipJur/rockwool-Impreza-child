/**
 * Admin JavaScript Entry Point
 * Contains only admin-specific functionality to avoid loading frontend bloat
 */

import { setupBusinessModal } from './features/admin/business-data-modal.js';

/**
 * Admin Application Class
 * Lightweight admin-only functionality
 */
class AdminApp {
  constructor() {
    this.modules = {
      businessModal: null
    };
    this.isInitialized = false;

    this.init();
  }

  /**
   * Initialize the admin application
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
    this.isInitialized = true;

    console.log('Admin application initialized successfully');
  }

  /**
   * Initialize admin-specific modules
   */
  initializeModules() {
    // Business data modal for user management
    try {
      this.modules.businessModal = setupBusinessModal();
      console.log('Business data modal initialized');
    } catch (error) {
      console.error('Failed to initialize business data modal:', error);
    }
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
    Object.keys(this.modules).forEach(key => {
      if (this.modules[key] && typeof this.modules[key].cleanup === 'function') {
        this.modules[key].cleanup();
      } else if (this.modules[key] && typeof this.modules[key].destroy === 'function') {
        this.modules[key].destroy();
      }
      this.modules[key] = null;
    });

    this.isInitialized = false;
    console.log('Admin application destroyed');
  }
}

// Initialize the admin application
const adminApp = new AdminApp();

// Make admin app available globally for debugging
window.AdminApp = adminApp;

// Development debugging
if (process.env.NODE_ENV === 'development') {
  window.AdminAppDebug = {
    app: adminApp,
    modules: adminApp.modules,
  };
}
