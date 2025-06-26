/**
 * Admin JavaScript Entry Point
 * Contains only admin-specific functionality to avoid loading frontend bloat
 */

import { setupBusinessModal } from './features/admin/business-data-modal.js';
import { setupAresForm } from './features/ares/handler.js';
import { setupRealizaceManagement } from './features/admin/RealizaceManagement.js';
import { setupFakturyManagement } from './features/admin/FakturyManagement.js';

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
      fakturyManagement: null
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
    this.setupEventListeners();
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

  }

  /**
   * Setup event listeners for admin functionality
   */
  setupEventListeners() {
    // Handle reject button clicks in post editor
    document.body.addEventListener('click', (event) => {
      if (event.target.id === 'mistr-fachman-reject-button') {
        event.preventDefault();
        this.handleEditorRejectClick(event.target);
      }
    });
  }

  /**
   * Handle editor reject button click
   */
  async handleEditorRejectClick(button) {
    const postId = button.dataset.postId;
    const nonce = button.dataset.nonce;
    const actionType = button.dataset.actionType; // 'reject', 'revoke', or 'disabled'

    // Don't do anything if button is disabled (already rejected)
    if (actionType === 'disabled' || button.disabled) {
      return;
    }

    const confirmMessage = actionType === 'revoke' 
      ? 'Opravdu chcete zrušit schválení této položky? Tím se vrátí do stavu "Čeká na schválení" a body budou uživateli odebrány.'
      : 'Opravdu chcete tuto položku odmítnout?';

    if (!confirm(confirmMessage)) {
      return;
    }

    const reason = prompt('Zadejte prosím důvod pro odmítnutí/zrušení (nepovinné):', '');
    
    // Proceed even if the user cancels the prompt or leaves the reason empty
    if (reason === null) { // User clicked cancel
      return;
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Zpracovává se...';

    const formData = new FormData();
    formData.append('action', `mistr_fachman_${window.typenow}_editor_reject`);
    formData.append('post_id', postId);
    formData.append('nonce', nonce);
    formData.append('reason', reason);

    try {
      const response = await fetch(window.ajaxurl, {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        alert(data.data.message);
        window.location.href = data.data.redirect_url;
      } else {
        throw new Error(data.data.message);
      }
    } catch (error) {
      alert('Chyba: ' + error.message);
      button.disabled = false;
      button.textContent = originalText;
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
