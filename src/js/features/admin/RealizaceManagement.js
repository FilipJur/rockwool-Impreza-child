/**
 * Realizace Management Class
 * 
 * Domain-specific implementation of admin management for Realizace.
 * Extends AdminManagementBase with Realizace-specific configuration and behavior.
 * 
 * @extends AdminManagementBase
 * @since 1.0.0
 */

import { AdminManagementBase } from './base/AdminManagementBase.js';
import { AdminConfig } from './base/AdminConfig.js';
import initManager from '../../utils/initialization-manager.js';

export class RealizaceManagement extends AdminManagementBase {
  /**
   * Create new Realizace management instance
   * 
   * @param {Object|AdminConfig} config - Configuration object or AdminConfig instance
   */
  constructor(config) {
    // Ensure we have an AdminConfig instance
    if (!(config instanceof AdminConfig)) {
      const globalData = config.globalData || window.mistrRealizaceAdmin || {};
      config = AdminConfig.createForDomain('realization', globalData);
      
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
    return 'Realizace';
  }

  /**
   * Get domain slug for CSS classes and endpoints
   * 
   * @returns {string} Domain slug
   */
  getDomainSlug() {
    return 'realization';
  }

  /**
   * Get WordPress post type for this domain
   * 
   * @returns {string} Post type (used for AJAX actions)
   */
  getPostType() {
    return 'realization';
  }

  /**
   * Get field names mapping from centralized config
   * 
   * @returns {Object} Field names object
   */
  getFieldNames() {
    const globalFieldNames = this.config.globalData?.field_names || {};
    
    return {
      rejection_reason: globalFieldNames.rejection_reason || 'rejection_reason',
      points: globalFieldNames.points || 'points_assigned',
      gallery: globalFieldNames.gallery || 'realization_gallery',
      area: globalFieldNames.area || 'area_sqm',
      construction_type: globalFieldNames.construction_type || 'construction_type',
      materials: globalFieldNames.materials || 'materials_used'
    };
  }

  /**
   * Get default values for form inputs
   * 
   * @returns {Object} Default values object
   */
  getDefaultValues() {
    const globalDefaults = this.config.globalData?.default_values || {};
    
    return {
      points: globalDefaults.points || 2500,
      status: 'pending'
    };
  }

  /**
   * Get localized messages from WordPress
   * 
   * @returns {Object} Messages object
   */
  getMessages() {
    const globalMessages = this.config.globalData?.messages || {};
    
    return {
      confirm_approve: globalMessages.confirm_approve || 'Opravdu chcete schválit tuto realizaci?',
      confirm_reject: globalMessages.confirm_reject || 'Opravdu chcete odmítnout tuto realizaci?',
      confirm_bulk_approve: globalMessages.confirm_bulk_approve || 'Opravdu chcete hromadně schválit všechny čekající realizace tohoto uživatele?',
      processing: globalMessages.processing || 'Zpracovává se...',
      error_generic: globalMessages.error_generic || 'Došlo k chybě při zpracování požadavku.',
      approve_text: globalMessages.approve_text || 'Schválit',
      reject_text: globalMessages.reject_text || 'Odmítnout',
      bulk_approve_text: globalMessages.bulk_approve_text || 'Hromadně schválit',
      save_rejection_text: 'Uložit důvod',
      success_approve: 'Realizace byla úspěšně schválena.',
      success_reject: 'Realizace byla odmítnuta.',
      success_bulk_approve: 'Realizace byly hromadně schváleny.',
      success_save_rejection: 'Důvod odmítnutí byl uložen do ACF pole.'
    };
  }


  /**
   * Realizace-specific notification handling
   * Enhanced with custom styling for Realizace domain
   * 
   * @param {string} message - Notification message
   * @param {string} type - Notification type
   */
  showNotification(message, type = 'info') {
    // Remove existing notifications
    document.querySelectorAll('.realizace-notification').forEach(el => el.remove());

    const notification = document.createElement('div');
    notification.className = `realizace-notification notice notice-${type} is-dismissible`;
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
   * Enhanced initialization with Realizace-specific setup
   */
  init() {
    // Prevent double initialization using centralized manager
    if (initManager.isInitialized('realization-management')) {
      console.warn('[Realizace] Management already initialized elsewhere');
      return;
    }
    
    initManager.setInitialized('realization-management', this);
    super.init();

    // Realizace-specific initialization
    this.initializeGalleryHandlers();
    this.setupRealizaceSpecificEvents();
  }

  /**
   * Initialize gallery interaction handlers
   * Domain-specific functionality for Realizace image galleries
   */
  initializeGalleryHandlers() {
    // Add click handlers for gallery images
    document.addEventListener('click', (event) => {
      const galleryImage = event.target.closest('.gallery-image img');
      if (!galleryImage) return;

      // Simple lightbox functionality
      const modal = document.createElement('div');
      modal.className = 'realizace-image-modal';
      modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.8); z-index: 100000; display: flex;
        align-items: center; justify-content: center; cursor: pointer;
      `;
      
      const img = document.createElement('img');
      img.src = galleryImage.src;
      img.style.cssText = 'max-width: 90%; max-height: 90%; object-fit: contain;';
      
      modal.appendChild(img);
      document.body.appendChild(modal);
      
      // Close on click
      modal.addEventListener('click', () => {
        modal.remove();
      });
      
      // Close on escape key
      const handleEscape = (e) => {
        if (e.key === 'Escape') {
          modal.remove();
          document.removeEventListener('keydown', handleEscape);
        }
      };
      document.addEventListener('keydown', handleEscape);
    });
  }

  /**
   * Setup Realizace-specific event handlers
   */
  setupRealizaceSpecificEvents() {
    // Add any Realizace-specific event handlers here
    console.log('[Realizace] Specific events initialized');
  }

  /**
   * Enhanced cleanup with Realizace-specific cleanup
   */
  cleanup() {
    super.cleanup();
    
    // Clean up using centralized manager
    initManager.setUninitialized('realization-management');
  }
}

/**
 * Factory function for backward compatibility
 * Creates and returns a RealizaceManagement instance
 * 
 * @param {string} containerSelector - CSS selector for management container
 * @returns {Object} Management instance with cleanup method
 */
export function setupRealizaceManagement(containerSelector = '.realizace-management-modern') {
  console.log('[Realizace] Setting up management...');

  const config = {
    containerSelector,
    globalData: window.mistrRealizaceAdmin || {}
  };
  
  const management = new RealizaceManagement(config);
  
  return {
    cleanup: () => management.cleanup(),
    isInitialized: () => management.isInitialized(),
    instance: management
  };
}