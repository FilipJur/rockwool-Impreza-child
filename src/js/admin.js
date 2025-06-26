/**
 * Admin JavaScript Entry Point
 * Contains only admin-specific functionality to avoid loading frontend bloat
 */

import { setupBusinessModal } from './features/admin/business-data-modal.js';
import { setupAresForm } from './features/ares/handler.js';
import { setupRealizaceManagement } from './features/admin/RealizaceManagement.js';
import { setupFakturyManagement } from './features/admin/FakturyManagement.js';
import { StatusDropdownManager } from './features/admin/StatusDropdownManager.js';

/**
 * Admin Application Class
 * Lightweight admin-only functionality
 */
class AdminApp {
  constructor() {
    this.modules = {
      businessModal: null,
      aresHandler: null,
      realizaceManagement: null,
      fakturyManagement: null,
      statusDropdown: null
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
    const realizaceContainer = document.querySelector('.realization-management-modern');
    console.log('[Admin] Looking for realizace container:', realizaceContainer);
    console.log('[Admin] All elements with "realization" in class:', document.querySelectorAll('[class*="realization"]'));
    
    if (realizaceContainer) {
      try {
        this.modules.realizaceManagement = setupRealizaceManagement('.realization-management-modern');
        console.log('[Admin] Realizace management initialized for admin');
      } catch (error) {
        console.error('[Admin] Failed to initialize realizace management for admin:', error);
      }
    } else {
      console.log('[Admin] No realizace container found - skipping initialization');
    }

    // Faktury management for user admin interface
    if (document.querySelector('.invoice-management-modern')) {
      try {
        this.modules.fakturyManagement = setupFakturyManagement('.invoice-management-modern');
        console.log('[Admin] Faktury management initialized for admin');
      } catch (error) {
        console.error('[Admin] Failed to initialize faktury management for admin:', error);
      }
    }

    // Status dropdown manager for post edit pages
    if (window.mistrFachmanStatusDropdown) {
      try {
        this.modules.statusDropdown = new StatusDropdownManager(window.mistrFachmanStatusDropdown);
        console.log('[Admin] Status dropdown manager initialized');
      } catch (error) {
        console.error('[Admin] Failed to initialize status dropdown manager:', error);
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

// Debug: Confirm script is loading
console.log('[Admin] admin.js script is executing');
console.log('[Admin] Current URL:', window.location.href);
console.log('[Admin] Document ready state:', document.readyState);

// Add temporary visible debug indicator
if (document.readyState !== 'loading') {
  const debugDiv = document.createElement('div');
  debugDiv.style.cssText = 'position:fixed;top:10px;right:10px;background:red;color:white;padding:10px;z-index:99999;font-size:12px;';
  debugDiv.textContent = 'Admin.js Loaded';
  document.body.appendChild(debugDiv);
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
