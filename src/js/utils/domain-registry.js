/**
 * Domain Registry JavaScript Utility
 *
 * JavaScript mirror of the PHP DomainRegistry service.
 * Provides centralized domain configuration for frontend code.
 * Prevents AJAX action naming mismatches between PHP and JavaScript.
 *
 * @since 1.0.0
 */

export class DomainRegistry {
  /**
   * Domain configuration registry
   * Mirrors the PHP DomainRegistry::$domains array
   */
  static domains = {
    realizace: {
      postType: 'realizace',
      scriptPrefix: 'realizace',
      jsSlug: 'realizace',
      displayName: 'Realizace',
      displayNamePlural: 'Realizace'
    },
    faktury: {
      postType: 'faktura',        // WordPress post type (singular)
      scriptPrefix: 'faktury',    // Script handle prefix (plural)
      jsSlug: 'faktury',          // JavaScript domain slug (plural)
      displayName: 'Faktura',     // Display name (singular)
      displayNamePlural: 'Faktury' // Display name (plural)
    },
    // Future domains
    certifikaty: {
      postType: 'certifikat',
      scriptPrefix: 'certifikaty',
      jsSlug: 'certifikaty',
      displayName: 'Certifikát',
      displayNamePlural: 'Certifikáty'
    }
  };

  /**
   * Get WordPress post type for a domain
   * 
   * @param {string} domain - Domain key
   * @returns {string} Post type (used for AJAX hooks, database queries)
   * @throws {Error} If domain not found
   */
  static getPostType(domain) {
    const config = this.getDomainConfig(domain);
    return config.postType;
  }

  /**
   * Get script handle prefix for a domain
   * 
   * @param {string} domain - Domain key
   * @returns {string} Script prefix (used for CSS/JS handles)
   * @throws {Error} If domain not found
   */
  static getScriptPrefix(domain) {
    const config = this.getDomainConfig(domain);
    return config.scriptPrefix;
  }

  /**
   * Get JavaScript slug for a domain
   * 
   * @param {string} domain - Domain key
   * @returns {string} JS slug (used for CSS classes, frontend identifiers)
   * @throws {Error} If domain not found
   */
  static getJsSlug(domain) {
    const config = this.getDomainConfig(domain);
    return config.jsSlug;
  }

  /**
   * Get display name for a domain
   * 
   * @param {string} domain - Domain key
   * @param {boolean} plural - Whether to return plural form
   * @returns {string} Human-readable display name
   * @throws {Error} If domain not found
   */
  static getDisplayName(domain, plural = false) {
    const config = this.getDomainConfig(domain);
    return plural ? config.displayNamePlural : config.displayName;
  }

  /**
   * Get AJAX action prefix for a domain
   * 
   * CRITICAL: Always uses postType to ensure AJAX hooks match PHP
   * This prevents nonce mismatches between PHP and JavaScript
   * 
   * @param {string} domain - Domain key
   * @returns {string} AJAX prefix (always based on postType)
   * @throws {Error} If domain not found
   */
  static getAjaxPrefix(domain) {
    return this.getPostType(domain);
  }

  /**
   * Generate AJAX action name for a domain
   * 
   * @param {string} domain - Domain key
   * @param {string} action - Action type (e.g., 'quick_action', 'bulk_approve')
   * @returns {string} Complete AJAX action name
   * @throws {Error} If domain not found
   */
  static getAjaxAction(domain, action) {
    const prefix = this.getAjaxPrefix(domain);
    
    switch (action) {
      case 'quick_action':
        return `mistr_fachman_${prefix}_quick_action`;
      case 'bulk_approve':
        return `mistr_fachman_bulk_approve_${prefix}`;
      default:
        return `mistr_fachman_${prefix}_${action}`;
    }
  }

  /**
   * Generate nonce action name for a domain
   * 
   * @param {string} domain - Domain key
   * @param {string} action - Action type
   * @returns {string} Nonce action name
   * @throws {Error} If domain not found
   */
  static getNonceAction(domain, action) {
    const prefix = this.getAjaxPrefix(domain);
    
    switch (action) {
      case 'quick_action':
        return `mistr_fachman_${prefix}_action`;
      case 'bulk_approve':
        return `mistr_fachman_bulk_approve_${prefix}`;
      default:
        return `mistr_fachman_${prefix}_${action}`;
    }
  }

  /**
   * Get complete domain configuration
   * 
   * @param {string} domain - Domain key
   * @returns {Object} Complete domain configuration
   * @throws {Error} If domain not found
   */
  static getDomainConfig(domain) {
    if (!this.domains[domain]) {
      throw new Error(`Domain '${domain}' not found in registry`);
    }
    
    return this.domains[domain];
  }

  /**
   * Get all registered domains
   * 
   * @returns {Object} All domain configurations
   */
  static getAllDomains() {
    return this.domains;
  }

  /**
   * Check if domain is registered
   * 
   * @param {string} domain - Domain key to check
   * @returns {boolean} True if domain exists
   */
  static isDomainRegistered(domain) {
    return domain in this.domains;
  }

  /**
   * Create configuration object for AdminConfig
   * Provides all necessary endpoints and data for a domain
   * 
   * @param {string} domain - Domain key
   * @returns {Object} Configuration object for AdminConfig
   * @throws {Error} If domain not found
   */
  static createAdminConfigData(domain) {
    const config = this.getDomainConfig(domain);
    
    return {
      domain: domain,
      postType: config.postType,
      jsSlug: config.jsSlug,
      displayName: config.displayName,
      displayNamePlural: config.displayNamePlural,
      endpoints: {
        quickAction: this.getAjaxAction(domain, 'quick_action'),
        bulkApprove: this.getAjaxAction(domain, 'bulk_approve')
      },
      nonceActions: {
        quickAction: this.getNonceAction(domain, 'quick_action'),
        bulkApprove: this.getNonceAction(domain, 'bulk_approve')
      }
    };
  }

  /**
   * Validate domain configuration consistency
   * Checks for common Czech language pitfalls
   * 
   * @param {string} domain - Domain key to validate
   * @returns {Object} Validation results {valid: boolean, warnings: string[]}
   */
  static validateDomain(domain) {
    if (!this.isDomainRegistered(domain)) {
      return {
        valid: false,
        warnings: [`Domain '${domain}' not registered`]
      };
    }

    const config = this.domains[domain];
    const warnings = [];

    // Check for common Czech plural/singular mismatches
    const czechPlurals = {
      'faktura': 'faktury',
      'certifikat': 'certifikaty',
      'lokalizace': 'lokalizace' // Same form - OK
    };

    if (czechPlurals[config.postType] && czechPlurals[config.postType] !== config.jsSlug) {
      if (config.jsSlug === config.postType) {
        warnings.push(`JavaScript slug uses singular form '${config.jsSlug}' - consider plural '${czechPlurals[config.postType]}' for UI consistency`);
      }
    }

    // Ensure postType is used for AJAX consistency
    const ajaxPrefix = this.getAjaxPrefix(domain);
    if (ajaxPrefix !== config.postType) {
      warnings.push('AJAX prefix mismatch detected - this will cause nonce failures');
    }

    return {
      valid: warnings.length === 0 || warnings.length === 1, // UI warnings are OK
      warnings: warnings
    };
  }
}