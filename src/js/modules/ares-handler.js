/**
 * ARES Company Data Handler
 * Handles Czech company registration data lookup and form population
 */

import { dom } from '../utils/dom.js';
import { api } from '../utils/api.js';
import { validation } from '../utils/validation.js';

export class AresHandler {
  constructor() {
    this.form = null;
    this.fields = {
      ico: null,
      companyName: null,
      address: null,
      status: null,
      loadButton: null
    };
    this.lastFetchedIco = null;
    this.isInitialized = false;

    this.init();
  }

  /**
   * Initialize the ARES handler
   */
  init() {
    dom.ready(() => {
      this.setupElements();
      if (this.isValidSetup()) {
        this.bindEvents();
        this.isInitialized = true;
        console.log('ARES Handler initialized successfully');
      }
    });
  }

  /**
   * Set up DOM elements and validate they exist
   */
  setupElements() {
    this.form = dom.findOne('.registration-form');
    
    if (!this.form) {
      console.warn('Registration form container ".registration-form" not found.');
      return;
    }

    this.fields = {
      ico: dom.findOne('[name="ico"]', this.form),
      companyName: dom.findOne('[name="company-name"]', this.form),
      address: dom.findOne('[name="address"]', this.form),
      status: dom.findOne('#aresStatus', this.form),
      loadButton: dom.findOne('#loadAresData', this.form)
    };
  }

  /**
   * Validate that all required elements are present
   * @returns {boolean}
   */
  isValidSetup() {
    const requiredFields = ['ico', 'companyName', 'address', 'status', 'loadButton'];
    const missingFields = requiredFields.filter(field => !this.fields[field]);
    
    if (missingFields.length > 0) {
      console.warn(`ARES Handler: Missing required fields: ${missingFields.join(', ')}`);
      return false;
    }

    return true;
  }

  /**
   * Bind event listeners
   */
  bindEvents() {
    // Load ARES data button click
    dom.on(this.fields.loadButton, 'click', (e) => {
      e.preventDefault();
      this.loadAresData();
    });

    // IČO field input change
    dom.on(this.fields.ico, 'input', () => {
      this.handleIcoChange();
    });

    // Enter key in IČO field
    dom.on(this.fields.ico, 'keypress', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        this.loadAresData();
      }
    });
  }

  /**
   * Load company data from ARES API
   */
  async loadAresData() {
    const ico = dom.val(this.fields.ico).trim();
    
    // Validate IČO format
    const icoValidation = validation.ico(ico);
    if (!icoValidation.valid) {
      this.showError(icoValidation.error);
      this.clearFields();
      return;
    }

    this.showStatus('Ověřuji IČO...', 'loading');
    this.clearFields();
    this.lastFetchedIco = null;

    try {
      const data = await api.withTimeout(
        api.get(`https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/${ico}`),
        15000 // 15 second timeout
      );

      this.handleAresSuccess(data, ico);
    } catch (error) {
      this.handleAresError(error);
    }
  }

  /**
   * Handle successful ARES API response
   * @param {Object} data - ARES API response data
   * @param {string} ico - The IČO that was looked up
   */
  handleAresSuccess(data, ico) {
    if (data && data.obchodniJmeno) {
      dom.val(this.fields.companyName, data.obchodniJmeno);
      
      if (data.sidlo && data.sidlo.textovaAdresa) {
        dom.val(this.fields.address, data.sidlo.textovaAdresa);
      }
      
      this.showStatus('Údaje načteny', 'success');
      this.lastFetchedIco = ico;
    } else {
      this.showError('Společnost nenalezena nebo odpověď neobsahuje název');
    }
  }

  /**
   * Handle ARES API errors
   * @param {Error} error - Error object
   */
  handleAresError(error) {
    const errorMessages = {
      404: 'IČO nebylo nalezeno v ARES',
      400: 'Chybný formát IČO pro ARES (zkontrolujte)',
      500: 'Služba ARES je dočasně nedostupná. Zkuste později',
      503: 'Služba ARES je dočasně nedostupná. Zkuste později',
      timeout: 'Požadavek na ARES vypršel. Zkuste později',
      network: 'Chyba připojení k ARES API',
      default: 'Chyba při komunikaci s ARES API'
    };

    const errorMessage = api.handleError(error, errorMessages);
    this.showError(errorMessage);
    
    console.error('ARES API Error:', error);
  }

  /**
   * Handle changes to the IČO field
   */
  handleIcoChange() {
    const currentIco = dom.val(this.fields.ico).trim();
    const hasCompanyData = dom.val(this.fields.companyName) !== '' || dom.val(this.fields.address) !== '';

    if (this.lastFetchedIco && currentIco !== this.lastFetchedIco && hasCompanyData) {
      this.showStatus('IČO bylo změněno. Klikněte na "Načíst údaje" pro aktualizaci', 'warning');
    } else if (!this.lastFetchedIco && hasCompanyData && currentIco !== '') {
      this.showStatus('Zadejte IČO a klikněte na "Načíst údaje"', 'info');
    } else if (currentIco === '' && this.lastFetchedIco) {
      this.clearStatus();
      this.lastFetchedIco = null;
    }
  }

  /**
   * Show status message
   * @param {string} message - Status message
   * @param {string} type - Status type: 'loading', 'success', 'error', 'warning', 'info'
   */
  showStatus(message, type = 'info') {
    const colors = {
      loading: '#ff9500',
      success: '#4CAF50',
      error: '#f44336',
      warning: '#ff9500',
      info: '#2196F3'
    };

    dom.text(this.fields.status, message);
    dom.css(this.fields.status, 'color', colors[type] || colors.info);
  }

  /**
   * Show error message
   * @param {string} message - Error message
   */
  showError(message) {
    this.showStatus(message, 'error');
  }

  /**
   * Clear status message
   */
  clearStatus() {
    dom.text(this.fields.status, '');
    dom.css(this.fields.status, 'color', '');
  }

  /**
   * Clear company data fields
   */
  clearFields() {
    dom.val(this.fields.companyName, '');
    dom.val(this.fields.address, '');
  }

  /**
   * Check if handler is properly initialized
   * @returns {boolean}
   */
  isReady() {
    return this.isInitialized;
  }

  /**
   * Destroy the handler and clean up event listeners
   */
  destroy() {
    // Note: In a full implementation, you'd want to track and remove specific event listeners
    // For now, this is a placeholder for cleanup functionality
    this.isInitialized = false;
    this.lastFetchedIco = null;
    console.log('ARES Handler destroyed');
  }
}