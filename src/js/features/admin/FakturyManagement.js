/**
 * Faktury Management Class
 * 
 * Domain-specific implementation of admin management for Faktury (Invoices).
 * Extends AdminManagementBase with Faktury-specific configuration and behavior.
 * Demonstrates 80% code reuse from the base architecture.
 * 
 * @extends AdminManagementBase
 * @since 1.0.0
 */

import { AdminManagementBase } from './base/AdminManagementBase.js';
import { AdminConfig } from './base/AdminConfig.js';
import initManager from '../../utils/initialization-manager.js';

export class FakturyManagement extends AdminManagementBase {
  /**
   * Create new Faktury management instance
   * 
   * @param {Object|AdminConfig} config - Configuration object or AdminConfig instance
   */
  constructor(config) {
    // Ensure we have an AdminConfig instance
    if (!(config instanceof AdminConfig)) {
      const globalData = config.globalData || window.mistrFakturyAdmin || {};
      config = AdminConfig.createForDomain('faktury', globalData);
      
      // Override with any custom options from the original config
      if (config.containerSelector) {
        config.containerSelector = config.containerSelector;
      }
    }
    
    super(config);
  }

  /**
   * Get domain name for logging
   * 
   * @returns {string} Domain display name
   */
  getDomainName() {
    return 'Faktury';
  }

  /**
   * Get domain slug for CSS classes and endpoints
   * 
   * @returns {string} Domain slug
   */
  getDomainSlug() {
    return 'faktury';
  }

  /**
   * Get WordPress post type for this domain
   * 
   * @returns {string} Post type (used for AJAX actions)
   */
  getPostType() {
    return 'faktura';
  }

  /**
   * Get field names mapping for Faktury domain
   * 
   * @returns {Object} Field names object
   */
  getFieldNames() {
    const globalFieldNames = this.config.globalData?.field_names || {};
    
    return {
      rejection_reason: globalFieldNames.rejection_reason || 'faktury_duvod_zamitnuti',
      amount: globalFieldNames.amount || 'faktury_castka',
      due_date: globalFieldNames.due_date || 'faktury_splatnost',
      supplier: globalFieldNames.supplier || 'faktury_dodavatel',
      invoice_number: globalFieldNames.invoice_number || 'faktury_cislo',
      status: globalFieldNames.status || 'faktury_stav'
    };
  }

  /**
   * Get default values for Faktury forms
   * 
   * @returns {Object} Default values object
   */
  getDefaultValues() {
    const globalDefaults = this.config.globalData?.default_values || {};
    
    return {
      amount: globalDefaults.amount || 0,
      status: 'pending',
      points: 0 // Faktury points calculated from amount, not fixed
    };
  }

  /**
   * Get localized messages for Faktury domain
   * 
   * @returns {Object} Messages object
   */
  getMessages() {
    const globalMessages = this.config.globalData?.messages || {};
    
    return {
      confirm_approve: globalMessages.confirm_approve || 'Opravdu chcete schválit tuto fakturu?',
      confirm_reject: globalMessages.confirm_reject || 'Opravdu chcete odmítnout tuto fakturu?',
      confirm_bulk_approve: globalMessages.confirm_bulk_approve || 'Opravdu chcete hromadně schválit všechny čekající faktury tohoto uživatele?',
      processing: globalMessages.processing || 'Zpracovává se...',
      error_generic: globalMessages.error_generic || 'Došlo k chybě při zpracování požadavku.',
      approve_text: globalMessages.approve_text || 'Schválit',
      reject_text: globalMessages.reject_text || 'Odmítnout',
      bulk_approve_text: globalMessages.bulk_approve_text || 'Hromadně schválit',
      save_rejection_text: 'Uložit důvod',
      success_approve: 'Faktura byla úspěšně schválena.',
      success_reject: 'Faktura byla odmítnuta.',
      success_bulk_approve: 'Faktury byly hromadně schváleny.',
      success_save_rejection: 'Důvod odmítnutí byl uložen.'
    };
  }


  /**
   * Faktury-specific notification handling
   * Enhanced with custom styling for Faktury domain
   * 
   * @param {string} message - Notification message
   * @param {string} type - Notification type
   */
  showNotification(message, type = 'info') {
    // Remove existing notifications
    document.querySelectorAll('.faktury-notification').forEach(el => el.remove());

    const notification = document.createElement('div');
    notification.className = `faktury-notification notice notice-${type} is-dismissible`;
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

      // Auto-dismiss after 5 seconds for success messages
      if (type === 'success') {
        setTimeout(() => {
          if (notification.parentNode) {
            notification.remove();
          }
        }, 5000);
      }

      // Handle dismiss button
      const dismissButton = notification.querySelector('.notice-dismiss');
      if (dismissButton) {
        dismissButton.addEventListener('click', () => {
          notification.remove();
        });
      }
    } else {
      // Fallback to parent implementation
      super.showNotification(message, type);
    }
  }

  /**
   * Enhanced initialization with Faktury-specific setup
   */
  init() {
    // Prevent double initialization using centralized manager
    if (initManager.isInitialized('faktury-management')) {
      console.warn('[Faktury] Management already initialized elsewhere');
      return;
    }
    
    initManager.setInitialized('faktury-management', this);
    super.init();

    // Faktury-specific initialization
    this.initializeAmountValidation();
    this.setupFakturySpecificEvents();
  }

  /**
   * Initialize amount validation for Faktury
   * Domain-specific functionality for amount input validation
   */
  initializeAmountValidation() {
    // Add validation for amount inputs
    document.addEventListener('input', (event) => {
      if (event.target.matches('.faktury-amount-input')) {
        const input = event.target;
        const value = parseFloat(input.value);
        
        // Remove any non-numeric characters except decimal point
        input.value = input.value.replace(/[^0-9.]/g, '');
        
        // Validate minimum amount
        if (value < 0) {
          input.value = '0';
          this.showNotification('Částka nemůže být záporná.', 'warning');
        }
        
        // Validate maximum amount (example: 1,000,000)
        if (value > 1000000) {
          input.value = '1000000';
          this.showNotification('Částka nemůže přesáhnout 1,000,000 Kč.', 'warning');
        }
        
        // Calculate points based on amount (example: 1 point per 100 Kč)
        const calculatedPoints = Math.floor(value / 100);
        const pointsDisplay = input.closest('.faktury-item')?.querySelector('.calculated-points');
        if (pointsDisplay) {
          pointsDisplay.textContent = `${calculatedPoints} bodů`;
        }
      }
    });
  }

  /**
   * Setup Faktury-specific event handlers
   */
  setupFakturySpecificEvents() {
    // Add due date validation
    document.addEventListener('change', (event) => {
      if (event.target.matches('.faktury-due-date-input')) {
        const input = event.target;
        const dueDate = new Date(input.value);
        const today = new Date();
        
        if (dueDate < today) {
          this.showNotification('Datum splatnosti je v minulosti.', 'warning');
          input.classList.add('due-date-past');
        } else {
          input.classList.remove('due-date-past');
        }
      }
    });
    
    console.log('[Faktury] Specific events initialized');
  }

  /**
   * Override populateDefaultValues for Faktury-specific behavior
   */
  populateDefaultValues() {
    // Faktury doesn't use fixed points like Realizace
    // Points are calculated from amount, so we don't populate default points
    
    const defaultValues = this.getDefaultValues();
    
    // Populate default amount if empty
    if (defaultValues.amount !== undefined) {
      const amountInputs = document.querySelectorAll('.faktury-amount-input[data-post-id]');
      amountInputs.forEach(input => {
        if (!input.value || input.value === '0' || input.value === '') {
          input.value = defaultValues.amount.toString();
        }
      });
    }
  }

  /**
   * Enhanced cleanup with Faktury-specific cleanup
   */
  cleanup() {
    super.cleanup();
    
    // Clean up using centralized manager
    initManager.setUninitialized('faktury-management');
  }
}

/**
 * Factory function for Faktury management
 * Creates and returns a FakturyManagement instance
 * 
 * @param {string} containerSelector - CSS selector for management container
 * @returns {Object} Management instance with cleanup method
 */
export function setupFakturyManagement(containerSelector = '.faktury-management-modern') {
  console.log('Setting up faktury management...');

  const config = {
    containerSelector,
    globalData: window.mistrFakturyAdmin || {}
  };
  
  const management = new FakturyManagement(config);
  
  return {
    cleanup: () => management.cleanup(),
    isInitialized: () => management.isInitialized(),
    instance: management
  };
}