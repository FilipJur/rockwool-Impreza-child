/**
 * ARES Company Data Handler
 * Handles Czech company registration data lookup and form population
 */

import { api } from '../../utils/api.js';
import { validation } from '../../utils/validation.js';

/**
 * Initialize ARES form functionality
 * @param {string} formSelector - CSS selector for the form container
 * @returns {Object|null} Handler object with cleanup method, or null if setup fails
 */
export function setupAresForm(formSelector = '.registration-form') {
  const form = document.querySelector(formSelector);
  
  if (!form) {
    console.warn(`ARES Handler: Form container "${formSelector}" not found.`);
    return null;
  }

  const fields = {
    ico: form.querySelector('[name="ico"]'),
    companyName: form.querySelector('[name="company-name"]'),
    address: form.querySelector('[name="address"]'),
    status: form.querySelector('#aresStatus'),
    loadButton: form.querySelector('#loadAresData')
  };

  // Validate required fields
  const requiredFields = ['ico', 'companyName', 'address', 'status', 'loadButton'];
  const missingFields = requiredFields.filter(field => !fields[field]);

  if (missingFields.length > 0) {
    console.warn(`ARES Handler: Missing required fields: ${missingFields.join(', ')}`);
    return null;
  }

  let lastFetchedIco = null;
  const eventListeners = [];

  // Event handler functions
  const handleLoadClick = (e) => {
    e.preventDefault();
    loadAresData();
  };

  const handleIcoInput = () => {
    handleIcoChange();
  };

  const handleIcoKeypress = (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      loadAresData();
    }
  };

  // Bind event listeners and track them for cleanup
  fields.loadButton.addEventListener('click', handleLoadClick);
  eventListeners.push({ element: fields.loadButton, event: 'click', handler: handleLoadClick });

  fields.ico.addEventListener('input', handleIcoInput);
  eventListeners.push({ element: fields.ico, event: 'input', handler: handleIcoInput });

  fields.ico.addEventListener('keypress', handleIcoKeypress);
  eventListeners.push({ element: fields.ico, event: 'keypress', handler: handleIcoKeypress });

  /**
   * Load company data from ARES API
   */
  async function loadAresData() {
    const ico = fields.ico.value.trim();

    // Validate IČO format
    const icoValidation = validation.ico(ico);
    if (!icoValidation.valid) {
      showError(icoValidation.error);
      clearFields();
      return;
    }

    showStatus('Ověřuji IČO...', 'loading');
    clearFields();
    lastFetchedIco = null;

    try {
      const data = await api.withTimeout(
        api.get(`https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/${ico}`),
        15000 // 15 second timeout
      );

      handleAresSuccess(data, ico);
    } catch (error) {
      handleAresError(error);
    }
  }

  /**
   * Handle successful ARES API response
   * @param {Object} data - ARES API response data
   * @param {string} ico - The IČO that was looked up
   */
  function handleAresSuccess(data, ico) {
    if (data && data.obchodniJmeno) {
      // Populate fields with ARES data
      fields.companyName.value = data.obchodniJmeno;

      if (data.sidlo && data.sidlo.textovaAdresa) {
        fields.address.value = data.sidlo.textovaAdresa;
      }

      // Lock fields after successful ARES validation
      lockAresFields();
      showStatus('Údaje načteny z ARES a ověřeny - tyto údaje budou použity při registraci', 'success');
      lastFetchedIco = ico;
    } else {
      showError('Společnost nenalezena nebo odpověď neobsahuje název');
      unlockAresFields();
    }
  }

  /**
   * Handle ARES API errors
   * @param {Error} error - Error object
   */
  function handleAresError(error) {
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
    showError(errorMessage + ' - Můžete vyplnit údaje ručně');
    
    // Unlock fields when ARES fails, allowing manual input
    unlockAresFields();

    console.error('ARES API Error:', error);
  }

  /**
   * Handle changes to the IČO field
   */
  function handleIcoChange() {
    const currentIco = fields.ico.value.trim();
    const hasCompanyData = fields.companyName.value !== '' || fields.address.value !== '';

    if (lastFetchedIco && currentIco !== lastFetchedIco && hasCompanyData) {
      showStatus('IČO bylo změněno. Klikněte na "Načíst údaje" pro aktualizaci', 'warning');
      // Unlock fields when IČO changes after successful fetch
      unlockAresFields();
    } else if (!lastFetchedIco && hasCompanyData && currentIco !== '') {
      showStatus('Zadejte IČO a klikněte na "Načíst údaje"', 'info');
    } else if (currentIco === '' && lastFetchedIco) {
      clearStatus();
      unlockAresFields();
      lastFetchedIco = null;
    }
  }

  /**
   * Show status message
   * @param {string} message - Status message
   * @param {string} type - Status type: 'loading', 'success', 'error', 'warning', 'info'
   */
  function showStatus(message, type = 'info') {
    const colors = {
      loading: '#ff9500',
      success: '#4CAF50',
      error: '#D32F2F',
      warning: '#ff9500',
      info: '#2196F3'
    };

    fields.status.textContent = message;
    fields.status.style.color = colors[type] || colors.info;
  }

  /**
   * Show error message
   * @param {string} message - Error message
   */
  function showError(message) {
    showStatus(message, 'error');
  }

  /**
   * Clear status message
   */
  function clearStatus() {
    fields.status.textContent = '';
    fields.status.style.color = '';
  }

  /**
   * Clear company data fields
   */
  function clearFields() {
    fields.companyName.value = '';
    fields.address.value = '';
    // Also unlock fields when clearing
    unlockAresFields();
  }

  /**
   * Lock ARES-fetched fields to prevent editing
   */
  function lockAresFields() {
    const fieldsToLock = [fields.companyName, fields.address];
    
    fieldsToLock.forEach(field => {
      field.readOnly = true;
      field.classList.add('ares-verified');
      field.setAttribute('title', 'Údaj ověřen přes ARES - bude použit přesně při registraci. Pro úpravu změňte IČO.');
    });
  }

  /**
   * Unlock ARES fields for manual editing
   */
  function unlockAresFields() {
    const fieldsToUnlock = [fields.companyName, fields.address];
    
    fieldsToUnlock.forEach(field => {
      field.readOnly = false;
      field.classList.remove('ares-verified');
      field.removeAttribute('title');
    });
  }


  /**
   * Cleanup function to remove event listeners
   */
  function cleanup() {
    eventListeners.forEach(({ element, event, handler }) => {
      element.removeEventListener(event, handler);
    });
    eventListeners.length = 0;
    lastFetchedIco = null;
    console.log('ARES Handler cleaned up');
  }

  console.log('ARES Handler initialized successfully');

  // Return handler object with cleanup method
  return {
    cleanup,
    isReady: () => true,
    loadAresData,
    clearFields,
    showStatus,
    showError,
    clearStatus,
    lockAresFields,
    unlockAresFields
  };
}
