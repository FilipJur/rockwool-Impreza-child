/**
 * ARES Company Data Handler
 * Handles Czech company registration data lookup and form population
 */

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
      // Use WordPress AJAX endpoint instead of direct ARES API call
      const formData = new FormData();
      formData.append('action', 'mistr_fachman_validate_ico');
      formData.append('nonce', window.mistrFachmanAjax?.ico_validation_nonce || '');
      formData.append('ico', ico);

      const response = await fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });

      const responseData = await response.json();

      if (!responseData.success) {
        throw new Error(responseData.data?.message || 'Neznámá chyba serveru');
      }

      handleAresSuccess(responseData.data, ico);
    } catch (error) {
      handleAresError(error);
    }
  }

  /**
   * Handle successful ARES API response
   * @param {Object} data - WordPress AJAX response data
   * @param {string} ico - The IČO that was looked up
   */
  function handleAresSuccess(data, ico) {
    if (data && data.company_name) {
      // Populate fields with ARES data from WordPress endpoint
      fields.companyName.value = data.company_name;
      fields.address.value = data.address;

      // Lock fields after successful ARES validation
      lockAresFields();
      showStatus('Údaje načteny z ARES a ověřeny.', 'success');
      lastFetchedIco = ico;
    } else {
      showError('Odpověď ze serveru byla neplatná.');
      unlockAresFields();
    }
  }

  /**
   * Handle ARES validation errors
   * @param {Error} error - Error object
   */
  function handleAresError(error) {
    // The error message is already user-friendly from the WordPress endpoint
    const errorMessage = error?.message || 'Došlo k neznámé chybě.';
    showError(errorMessage);
    unlockAresFields();
    console.error('IČO Validation Error:', error);
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
      error: 'var(--color-primary)',
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
