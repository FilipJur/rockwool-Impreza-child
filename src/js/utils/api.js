/**
 * API utility functions
 * Modern fetch-based AJAX helpers
 */

export const api = {
  /**
   * Make GET request
   * @param {string} url - Request URL
   * @param {Object} options - Request options
   * @returns {Promise}
   */
  async get(url, options = {}) {
    return this.request(url, { method: 'GET', ...options });
  },

  /**
   * Make POST request
   * @param {string} url - Request URL
   * @param {Object} data - Request data
   * @param {Object} options - Request options
   * @returns {Promise}
   */
  async post(url, data = {}, options = {}) {
    return this.request(url, {
      method: 'POST',
      body: JSON.stringify(data),
      headers: {
        'Content-Type': 'application/json',
        ...options.headers
      },
      ...options
    });
  },

  /**
   * Generic request handler
   * @param {string} url - Request URL
   * @param {Object} options - Fetch options
   * @returns {Promise}
   */
  async request(url, options = {}) {
    const defaults = {
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'same-origin'
    };

    const config = { ...defaults, ...options };
    
    try {
      const response = await fetch(url, config);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        return await response.json();
      } else {
        return await response.text();
      }
    } catch (error) {
      console.error('API Request failed:', error);
      throw error;
    }
  },


  /**
   * Handle API errors with user-friendly messages
   * @param {Error} error - Error object
   * @param {Object} messages - Custom error messages
   * @returns {string} User-friendly error message
   */
  handleError(error, messages = {}) {
    const defaultMessages = {
      404: 'Požadovaný zdroj nebyl nalezen',
      400: 'Chybný formát požadavku',
      500: 'Chyba serveru',
      503: 'Služba je dočasně nedostupná',
      network: 'Chyba připojení k internetu',
      timeout: 'Požadavek vypršel',
      default: 'Nastala neočekávaná chyba'
    };

    const errorMessages = { ...defaultMessages, ...messages };

    if (error.message.includes('HTTP 404')) {
      return errorMessages[404];
    } else if (error.message.includes('HTTP 400')) {
      return errorMessages[400];
    } else if (error.message.includes('HTTP 500')) {
      return errorMessages[500];
    } else if (error.message.includes('HTTP 503')) {
      return errorMessages[503];
    } else if (error.name === 'TypeError' && error.message.includes('fetch')) {
      return errorMessages.network;
    } else if (error.name === 'AbortError') {
      return errorMessages.timeout;
    } else {
      return errorMessages.default;
    }
  },

  /**
   * Add request timeout
   * @param {Promise} request - Request promise
   * @param {number} timeout - Timeout in milliseconds
   * @returns {Promise}
   */
  withTimeout(request, timeout = 10000) {
    return Promise.race([
      request,
      new Promise((_, reject) => 
        setTimeout(() => reject(new Error('Request timeout')), timeout)
      )
    ]);
  }
};

/**
 * WordPress-specific AJAX helper with nonce support
 * @param {string} url - Request URL 
 * @param {Object} options - Fetch options
 * @param {string} action - WordPress action for nonce
 * @returns {Promise} JSON response
 */
export async function fetchWithNonce(url, options = {}, action = '') {
  // Get nonce from WordPress localized data or generate it
  const nonce = window.wpApiSettings?.nonce || 
                window.mistrFachmanAjax?.nonce || 
                await generateNonce(action);
  
  // Add nonce to FormData if it exists
  if (options.body instanceof FormData) {
    options.body.append('nonce', nonce);
  } else if (options.body && typeof options.body === 'object') {
    options.body.nonce = nonce;
  }

  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': nonce,
      ...options.headers
    },
    ...options
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
  }

  return await response.json();
}

/**
 * Generate WordPress nonce for given action
 * @param {string} action - Action name
 * @returns {Promise<string>} Nonce value
 */
async function generateNonce(action) {
  // For admin pages, we can use a meta tag approach
  const nonceMeta = document.querySelector(`meta[name="wp-nonce-${action}"]`);
  if (nonceMeta) {
    return nonceMeta.getAttribute('content');
  }
  
  // Fallback - this would need to be provided by PHP
  console.warn('No nonce found for action:', action);
  return '';
}