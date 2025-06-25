/**
 * Admin JavaScript Entry Point
 * Contains only admin-specific functionality to avoid loading frontend bloat
 */

import { setupBusinessModal } from './features/admin/business-data-modal.js';
import { setupAresForm } from './features/ares/handler.js';
import { setupRealizaceManagement } from './features/admin/RealizaceManagement.js';

/**
 * Admin Application Class
 * Lightweight admin-only functionality
 */
class AdminApp {
  constructor() {
    this.modules = {
      businessModal: null,
      aresHandler: null,
      realizaceManagement: null
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

    console.log('[Admin] Application initialized successfully');
  }

  /**
   * Initialize admin-specific modules
   */
  initializeModules() {
    // Business data modal for user management
    try {
      this.modules.businessModal = setupBusinessModal();
      console.log('[Admin] Business data modal initialized');
    } catch (error) {
      console.error('[Admin] Failed to initialize business data modal:', error);
    }

    // ARES handler for IČO validation in admin forms (business data modal)
    // Look for forms that might contain IČO fields
    if (document.querySelector('[name="ico"]') || document.querySelector('#business-data-modal')) {
      try {
        // The ARES handler will look for forms containing IČO fields
        this.modules.aresHandler = setupAresForm('.business-data-form, .registration-form, form');
        console.log('[Admin] ARES handler initialized for admin');
      } catch (error) {
        console.error('[Admin] Failed to initialize ARES handler for admin:', error);
      }
    }

    // Realizace management for user admin interface
    if (document.querySelector('.realizace-management-modern')) {
      try {
        this.modules.realizaceManagement = setupRealizaceManagement('.realizace-management-modern');
        console.log('[Admin] Realizace management initialized for admin');
      } catch (error) {
        console.error('[Admin] Failed to initialize realizace management for admin:', error);
      }
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
    console.log('[Admin] Application destroyed');
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
