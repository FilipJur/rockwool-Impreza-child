/**
 * Admin Configuration Class
 * 
 * Centralized configuration management for admin domains.
 * Provides type-safe configuration objects with validation.
 * 
 * @since 1.0.0
 */

export class AdminConfig {
  /**
   * Create a new admin configuration
   * 
   * @param {string} domain - Domain name (e.g., 'realizace', 'faktury')
   * @param {Object} options - Configuration options
   * @param {Object} options.selectors - CSS selectors for domain elements
   * @param {Object} options.fieldNames - Domain-specific field names
   * @param {Object} options.messages - Localized messages
   * @param {Object} options.defaultValues - Default form values
   * @param {Object} options.endpoints - AJAX endpoint names
   * @param {Object} options.globalData - WordPress localized data
   */
  constructor(domain, options = {}) {
    this.domain = domain;
    this.validateDomain(domain);
    
    // Set configuration with defaults
    this.selectors = this.validateSelectors(options.selectors || {});
    this.fieldNames = this.validateFieldNames(options.fieldNames || {});
    this.messages = this.validateMessages(options.messages || {});
    this.defaultValues = this.validateDefaultValues(options.defaultValues || {});
    this.endpoints = this.validateEndpoints(options.endpoints || {});
    this.globalData = options.globalData || {};
    
    // Derived properties
    this.containerSelector = this.selectors.container || `.${domain}-management-modern`;
    this.sectionSelector = this.selectors.section || `.${domain}-section`;
  }

  /**
   * Validate domain name
   * 
   * @param {string} domain - Domain to validate
   * @throws {Error} If domain is invalid
   */
  validateDomain(domain) {
    if (!domain || typeof domain !== 'string') {
      throw new Error('Domain must be a non-empty string');
    }
    
    if (!/^[a-z][a-z0-9_]*$/.test(domain)) {
      throw new Error('Domain must start with lowercase letter and contain only letters, numbers, and underscores');
    }
  }

  /**
   * Validate and normalize selectors
   * 
   * @param {Object} selectors - Selectors to validate
   * @returns {Object} Validated selectors
   */
  validateSelectors(selectors) {
    const defaults = {
      container: `.${this.domain}-management-modern`,
      section: `.${this.domain}-section`,
      quickAction: '.action-approve, .action-reject',
      bulkAction: '.bulk-approve-btn, .bulk-reject-btn',
      saveRejection: '.save-rejection-btn',
      sectionToggle: '.section-toggle',
      pointsInput: '.quick-points-input',
      rejectionInput: '.rejection-reason-input'
    };

    return { ...defaults, ...selectors };
  }

  /**
   * Validate and normalize field names
   * 
   * @param {Object} fieldNames - Field names to validate
   * @returns {Object} Validated field names
   */
  validateFieldNames(fieldNames) {
    const required = ['rejection_reason'];
    const defaults = {
      rejection_reason: 'duvod_zamitnuti', // Fallback for backward compatibility
      points: 'pridelene_body',
      status: 'status'
    };

    const validated = { ...defaults, ...fieldNames };

    // Check required fields
    for (const field of required) {
      if (!validated[field]) {
        console.warn(`Missing required field name: ${field}`);
      }
    }

    return validated;
  }

  /**
   * Validate and normalize messages
   * 
   * @param {Object} messages - Messages to validate
   * @returns {Object} Validated messages
   */
  validateMessages(messages) {
    const defaults = {
      confirm_approve: 'Opravdu chcete schválit tento záznam?',
      confirm_reject: 'Opravdu chcete odmítnout tento záznam?',
      confirm_bulk_approve: 'Opravdu chcete hromadně schválit všechny čekající záznamy tohoto uživatele?',
      processing: 'Zpracovává se...',
      error_generic: 'Došlo k chybě při zpracování požadavku.',
      approve_text: 'Schválit',
      reject_text: 'Odmítnout',
      bulk_approve_text: 'Hromadně schválit',
      save_rejection_text: 'Uložit důvod',
      success_approve: 'Záznam byl úspěšně schválen.',
      success_reject: 'Záznam byl odmítnut.',
      success_bulk_approve: 'Záznamy byly hromadně schváleny.',
      success_save_rejection: 'Důvod odmítnutí byl uložen.'
    };

    return { ...defaults, ...messages };
  }

  /**
   * Validate and normalize default values
   * 
   * @param {Object} defaultValues - Default values to validate
   * @returns {Object} Validated default values
   */
  validateDefaultValues(defaultValues) {
    const defaults = {
      points: 0,
      status: 'pending'
    };

    const validated = { ...defaults, ...defaultValues };

    // Validate points is a number
    if (validated.points && typeof validated.points !== 'number') {
      validated.points = parseInt(validated.points) || 0;
    }

    return validated;
  }

  /**
   * Validate and normalize endpoints
   * 
   * @param {Object} endpoints - Endpoints to validate
   * @returns {Object} Validated endpoints
   */
  validateEndpoints(endpoints) {
    const defaults = {
      quickAction: `mistr_fachman_${this.domain}_quick_action`,
      bulkAction: `mistr_fachman_bulk_approve_${this.domain}`,
      updateField: 'mistr_fachman_update_acf_field'
    };

    return { ...defaults, ...endpoints };
  }

  /**
   * Get container selector
   * 
   * @returns {string} Container CSS selector
   */
  getContainerSelector() {
    return this.containerSelector;
  }

  /**
   * Get section selector
   * 
   * @returns {string} Section CSS selector
   */
  getSectionSelector() {
    return this.sectionSelector;
  }

  /**
   * Get field name for specific field
   * 
   * @param {string} fieldType - Field type (e.g., 'rejection_reason', 'points')
   * @returns {string|null} Field name or null if not found
   */
  getFieldName(fieldType) {
    return this.fieldNames[fieldType] || null;
  }

  /**
   * Get message for specific key
   * 
   * @param {string} messageKey - Message key
   * @returns {string|null} Message or null if not found
   */
  getMessage(messageKey) {
    return this.messages[messageKey] || null;
  }

  /**
   * Get default value for specific field
   * 
   * @param {string} valueKey - Value key (e.g., 'points', 'status')
   * @returns {*} Default value or null if not found
   */
  getDefaultValue(valueKey) {
    return this.defaultValues[valueKey] || null;
  }

  /**
   * Get endpoint for specific action
   * 
   * @param {string} actionType - Action type (e.g., 'quickAction', 'bulkAction')
   * @returns {string|null} Endpoint name or null if not found
   */
  getEndpoint(actionType) {
    return this.endpoints[actionType] || null;
  }

  /**
   * Check if configuration is valid
   * 
   * @returns {boolean} True if configuration is valid
   */
  isValid() {
    return !!(
      this.domain &&
      this.selectors &&
      this.fieldNames &&
      this.messages &&
      this.defaultValues &&
      this.endpoints
    );
  }

  /**
   * Get configuration summary for debugging
   * 
   * @returns {Object} Configuration summary
   */
  getSummary() {
    return {
      domain: this.domain,
      containerSelector: this.containerSelector,
      sectionSelector: this.sectionSelector,
      fieldCount: Object.keys(this.fieldNames).length,
      messageCount: Object.keys(this.messages).length,
      endpointCount: Object.keys(this.endpoints).length,
      hasGlobalData: !!this.globalData && Object.keys(this.globalData).length > 0,
      isValid: this.isValid()
    };
  }

  /**
   * Create configuration for specific domain
   * 
   * @param {string} domain - Domain name
   * @param {Object} globalData - WordPress localized data
   * @returns {AdminConfig} Configured instance
   */
  static createForDomain(domain, globalData = {}) {
    const options = {
      globalData,
      fieldNames: globalData.field_names || {},
      messages: globalData.messages || {},
      defaultValues: globalData.default_values || {},
      selectors: {
        container: `.${domain}-management-modern`
      },
      endpoints: {
        quickAction: `mistr_fachman_${domain}_quick_action`,
        bulkAction: `mistr_fachman_bulk_approve_${domain}`
      }
    };

    return new AdminConfig(domain, options);
  }
}