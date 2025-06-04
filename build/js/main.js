/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/js/modules/ares-handler.js":
/*!****************************************!*\
  !*** ./src/js/modules/ares-handler.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   AresHandler: () => (/* binding */ AresHandler)
/* harmony export */ });
/* harmony import */ var _utils_api_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../utils/api.js */ "./src/js/utils/api.js");
/* harmony import */ var _utils_validation_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../utils/validation.js */ "./src/js/utils/validation.js");
/**
 * ARES Company Data Handler
 * Handles Czech company registration data lookup and form population
 */



class AresHandler {
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
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.setup());
    } else {
      this.setup();
    }
  }
  setup() {
    this.setupElements();
    if (this.isValidSetup()) {
      this.bindEvents();
      this.isInitialized = true;
      console.log('ARES Handler initialized successfully');
    }
  }

  /**
   * Set up DOM elements and validate they exist
   */
  setupElements() {
    this.form = document.querySelector('.registration-form');
    if (!this.form) {
      console.warn('Registration form container ".registration-form" not found.');
      return;
    }
    this.fields = {
      ico: this.form.querySelector('[name="ico"]'),
      companyName: this.form.querySelector('[name="company-name"]'),
      address: this.form.querySelector('[name="address"]'),
      status: this.form.querySelector('#aresStatus'),
      loadButton: this.form.querySelector('#loadAresData')
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
    this.fields.loadButton.addEventListener('click', e => {
      e.preventDefault();
      this.loadAresData();
    });

    // IČO field input change
    this.fields.ico.addEventListener('input', () => {
      this.handleIcoChange();
    });

    // Enter key in IČO field
    this.fields.ico.addEventListener('keypress', e => {
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
    const ico = this.fields.ico.value.trim();

    // Validate IČO format
    const icoValidation = _utils_validation_js__WEBPACK_IMPORTED_MODULE_1__.validation.ico(ico);
    if (!icoValidation.valid) {
      this.showError(icoValidation.error);
      this.clearFields();
      return;
    }
    this.showStatus('Ověřuji IČO...', 'loading');
    this.clearFields();
    this.lastFetchedIco = null;
    try {
      const data = await _utils_api_js__WEBPACK_IMPORTED_MODULE_0__.api.withTimeout(_utils_api_js__WEBPACK_IMPORTED_MODULE_0__.api.get(`https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/${ico}`), 15000 // 15 second timeout
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
      this.fields.companyName.value = data.obchodniJmeno;
      if (data.sidlo && data.sidlo.textovaAdresa) {
        this.fields.address.value = data.sidlo.textovaAdresa;
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
    const errorMessage = _utils_api_js__WEBPACK_IMPORTED_MODULE_0__.api.handleError(error, errorMessages);
    this.showError(errorMessage);
    console.error('ARES API Error:', error);
  }

  /**
   * Handle changes to the IČO field
   */
  handleIcoChange() {
    const currentIco = this.fields.ico.value.trim();
    const hasCompanyData = this.fields.companyName.value !== '' || this.fields.address.value !== '';
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
      error: '#D32F2F',
      warning: '#ff9500',
      info: '#2196F3'
    };
    this.fields.status.textContent = message;
    this.fields.status.style.color = colors[type] || colors.info;
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
    this.fields.status.textContent = '';
    this.fields.status.style.color = '';
  }

  /**
   * Clear company data fields
   */
  clearFields() {
    this.fields.companyName.value = '';
    this.fields.address.value = '';
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

/***/ }),

/***/ "./src/js/modules/file-upload.js":
/*!***************************************!*\
  !*** ./src/js/modules/file-upload.js ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   FileUpload: () => (/* binding */ FileUpload)
/* harmony export */ });
/* harmony import */ var _utils_validation_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../utils/validation.js */ "./src/js/utils/validation.js");
/**
 * Enhanced File Upload Handler
 * Provides drag & drop functionality for Contact Form 7 file uploads
 */


class FileUpload {
  constructor() {
    // Singleton pattern
    if (FileUpload.instance) {
      return FileUpload.instance;
    }
    this.fileInput = null;
    this.originalContainer = null;
    this.customContainer = null;
    this.previewContainer = null;
    this.errorContainer = null;
    this.selectedFiles = [];
    this.config = {
      maxFileSize: 5 * 1024 * 1024,
      // 5MB
      allowedTypes: ["image/jpeg", "image/jpg", "image/png"],
      allowedExtensions: [".jpg", ".jpeg", ".png"]
    };
    this.isInitialized = false;
    FileUpload.instance = this;
    this.init();
  }

  /**
   * Initialize the file upload handler
   */
  init() {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => this.setup());
    } else {
      this.setup();
    }
  }

  /**
   * Set up the enhanced file upload interface
   */
  setup() {
    this.fileInput = document.querySelector('.registration-form input[name="fotky-realizace"]');
    if (!this.fileInput) {
      console.warn("File input not found");
      return;
    }
    this.originalContainer = this.fileInput.closest(".wpcf7-form-control-wrap");
    if (!this.originalContainer) {
      console.warn("File input container not found");
      return;
    }
    this.cleanup();
    this.createCustomInterface();
    this.bindEvents();
    this.syncWithOriginalInput();
    this.isInitialized = true;
    console.log("Enhanced File Upload initialized successfully");
  }

  /**
   * Create the custom drag & drop interface
   */
  createCustomInterface() {
    // Create upload zone
    this.customContainer = document.createElement("div");
    this.customContainer.className = "file-upload-zone";

    // File icon SVG
    const iconSvg = this.createFileIcon();
    const uploadText = document.createElement("div");
    uploadText.className = "file-upload-text";
    uploadText.innerHTML = "Přetáhněte fotky sem nebo<br><span>klikněte pro nahrání</span>";
    this.customContainer.innerHTML = iconSvg;
    this.customContainer.appendChild(uploadText);

    // Create preview container
    this.previewContainer = document.createElement("div");
    this.previewContainer.className = "file-preview-container";
    const previewList = document.createElement("div");
    previewList.className = "file-preview-list";
    this.previewContainer.appendChild(previewList);

    // Create error container
    this.errorContainer = document.createElement("div");
    this.errorContainer.className = "file-upload-error";

    // Insert before original container
    const parent = this.originalContainer.parentNode;
    parent.insertBefore(this.customContainer, this.originalContainer);
    parent.insertBefore(this.previewContainer, this.originalContainer);
    parent.insertBefore(this.errorContainer, this.originalContainer);
    this.hideOriginalElements();
  }

  /**
   * Create file icon SVG
   * @returns {string} SVG markup
   */
  createFileIcon() {
    return `<svg class="file-upload-icon" width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M3.55556 32C2.57778 32 1.74104 31.6521 1.04533 30.9564C0.34963 30.2607 0.00118519 29.4234 0 28.4444V3.55556C0 2.57778 0.348445 1.74104 1.04533 1.04533C1.74222 0.34963 2.57896 0.00118519 3.55556 0H28.4444C29.4222 0 30.2596 0.348445 30.9564 1.04533C31.6533 1.74222 32.0012 2.57896 32 3.55556V28.4444C32 29.4222 31.6521 30.2596 30.9564 30.9564C30.2607 31.6533 29.4234 32.0012 28.4444 32H3.55556ZM3.55556 28.4444H28.4444V3.55556H3.55556V28.4444ZM5.33333 24.8889H26.6667L20 16L14.6667 23.1111L10.6667 17.7778L5.33333 24.8889Z" fill="#CCCCCC"/>
    </svg>`;
  }

  /**
   * Hide original form elements
   */
  hideOriginalElements() {
    const br = this.originalContainer.nextElementSibling;
    if (br && br.tagName === "BR") {
      br.style.display = "none";
      const small = br.nextElementSibling;
      if (small && small.tagName === "SMALL") {
        small.style.display = "none";
      }
    }
  }

  /**
   * Show original form elements
   */
  showOriginalElements() {
    if (!this.originalContainer) return;
    const br = this.originalContainer.nextElementSibling;
    if (br && br.tagName === "BR") {
      br.style.display = "";
      const small = br.nextElementSibling;
      if (small && small.tagName === "SMALL") {
        small.style.display = "";
      }
    }
  }

  /**
   * Bind event listeners
   */
  bindEvents() {
    // Click to upload
    this.customContainer.addEventListener("click", () => {
      this.fileInput.click();
    });

    // File input change
    this.fileInput.addEventListener("change", e => {
      this.handleFileSelection(e.target.files);
    });

    // Drag and drop events
    this.bindDragDropEvents();

    // Prevent default drag behaviors globally
    this.preventDefaultDragBehaviors();
  }

  /**
   * Bind drag and drop event listeners
   */
  bindDragDropEvents() {
    this.customContainer.addEventListener("dragover", e => {
      e.preventDefault();
      e.stopPropagation();
      this.customContainer.classList.add("drag-over");
    });
    this.customContainer.addEventListener("dragleave", e => {
      e.preventDefault();
      e.stopPropagation();
      if (!this.customContainer.contains(e.relatedTarget)) {
        this.customContainer.classList.remove("drag-over");
      }
    });
    this.customContainer.addEventListener("drop", e => {
      e.preventDefault();
      e.stopPropagation();
      this.customContainer.classList.remove("drag-over");
      const files = Array.from(e.dataTransfer.files);
      this.setFilesToInput(files);
    });
  }

  /**
   * Prevent default drag behaviors on document
   */
  preventDefaultDragBehaviors() {
    ["dragenter", "dragover", "dragleave", "drop"].forEach(eventName => {
      document.addEventListener(eventName, e => {
        e.preventDefault();
        e.stopPropagation();
      }, false);
    });
  }

  /**
   * Set files to the original input element
   * @param {FileList|Array} files - Files to set
   */
  setFilesToInput(files) {
    if (!files || files.length === 0) return;
    try {
      if (typeof DataTransfer !== "undefined") {
        const dt = new DataTransfer();
        Array.from(files).forEach(file => {
          dt.items.add(file);
        });
        this.fileInput.files = dt.files;
        this.fileInput.dispatchEvent(new Event("change", {
          bubbles: true
        }));
      } else {
        this.showError("Drag and drop není podporován v tomto prohlížeči. Použijte tlačítko pro výběr souborů.");
      }
    } catch (error) {
      console.warn("DataTransfer not supported:", error);
      this.showError("Drag and drop není podporován. Použijte tlačítko pro výběr souborů.");
    }
  }

  /**
   * Handle file selection (both click and drag & drop)
   * @param {FileList} files - Selected files
   */
  handleFileSelection(files) {
    this.clearErrors();
    this.selectedFiles = [];
    if (!files || files.length === 0) {
      this.updatePreviewVisibility();
      return;
    }
    const validFiles = [];
    const errors = [];
    Array.from(files).forEach(file => {
      const fileValidation = this.validateFile(file);
      if (fileValidation.valid) {
        validFiles.push(file);
      } else {
        errors.push(`${file.name}: ${fileValidation.error}`);
      }
    });
    if (errors.length > 0) {
      this.showError(errors.join("<br>"));
    }
    if (validFiles.length > 0) {
      this.addFiles(validFiles);
    }
    this.updatePreviewVisibility();
  }

  /**
   * Validate a single file
   * @param {File} file - File to validate
   * @returns {Object} Validation result
   */
  validateFile(file) {
    // File type validation
    const typeValidation = _utils_validation_js__WEBPACK_IMPORTED_MODULE_0__.validation.fileType(file, this.config.allowedTypes);
    if (!typeValidation.valid) {
      return typeValidation;
    }

    // File size validation
    const sizeValidation = _utils_validation_js__WEBPACK_IMPORTED_MODULE_0__.validation.fileSize(file, this.config.maxFileSize);
    if (!sizeValidation.valid) {
      return sizeValidation;
    }
    return {
      valid: true
    };
  }

  /**
   * Add files to the preview
   * @param {Array} files - Valid files to add
   */
  addFiles(files) {
    files.forEach(file => {
      const fileData = {
        file: file,
        id: Date.now() + Math.random(),
        name: file.name,
        size: file.size,
        status: "selected"
      };
      this.selectedFiles.push(fileData);
      this.createFilePreview(fileData);
    });
  }

  /**
   * Create preview element for a file
   * @param {Object} fileData - File data object
   */
  createFilePreview(fileData) {
    const previewItem = document.createElement("div");
    previewItem.className = "file-preview-item";
    previewItem.dataset.fileId = fileData.id;

    // Thumbnail
    const thumbnail = document.createElement("div");
    thumbnail.className = "file-preview-thumbnail";
    if (fileData.file.type.startsWith("image/")) {
      const img = document.createElement("img");
      const reader = new FileReader();
      reader.onload = e => {
        img.src = e.target.result;
      };
      reader.readAsDataURL(fileData.file);
      thumbnail.appendChild(img);
    } else {
      thumbnail.innerHTML = "📄";
    }

    // File info
    const info = document.createElement("div");
    info.className = "file-preview-info";
    const name = document.createElement("div");
    name.className = "file-preview-name";
    name.textContent = fileData.name;
    const size = document.createElement("div");
    size.className = "file-preview-size";
    size.textContent = _utils_validation_js__WEBPACK_IMPORTED_MODULE_0__.validation.formatFileSize(fileData.size);
    const status = document.createElement("div");
    status.className = "file-preview-status";
    status.textContent = "Připraven k nahrání";
    info.appendChild(name);
    info.appendChild(size);
    info.appendChild(status);
    previewItem.appendChild(thumbnail);
    previewItem.appendChild(info);
    const previewList = this.previewContainer.querySelector(".file-preview-list");
    previewList.appendChild(previewItem);
  }

  /**
   * Sync with original input changes (e.g., from WPCF7 validation)
   */
  syncWithOriginalInput() {
    const observer = new MutationObserver(() => {
      if (this.fileInput.files && this.fileInput.files.length === 0) {
        this.selectedFiles = [];
        const previewList = this.previewContainer.querySelector(".file-preview-list");
        if (previewList) {
          previewList.innerHTML = "";
        }
        this.updatePreviewVisibility();
      }
    });
    observer.observe(this.fileInput, {
      attributes: true,
      attributeFilter: ["value"]
    });
  }

  /**
   * Update preview container visibility
   */
  updatePreviewVisibility() {
    if (this.selectedFiles.length > 0) {
      this.previewContainer.classList.add("has-files");
    } else {
      this.previewContainer.classList.remove("has-files");
    }
  }

  /**
   * Show error message
   * @param {string} message - Error message
   */
  showError(message) {
    this.errorContainer.innerHTML = message;
    this.errorContainer.classList.add("show");
    this.customContainer.classList.add("has-error");
    setTimeout(() => {
      this.clearErrors();
    }, 5000);
  }

  /**
   * Clear error messages
   */
  clearErrors() {
    this.errorContainer.classList.remove("show");
    this.customContainer.classList.remove("has-error");
  }

  /**
   * Reset the upload interface
   */
  reset() {
    this.selectedFiles = [];
    const previewList = this.previewContainer.querySelector(".file-preview-list");
    if (previewList) {
      previewList.innerHTML = "";
    }
    this.updatePreviewVisibility();
    this.clearErrors();
  }

  /**
   * Clean up and remove custom elements
   */
  cleanup() {
    if (this.customContainer && this.customContainer.parentNode) {
      this.customContainer.parentNode.removeChild(this.customContainer);
    }
    if (this.previewContainer && this.previewContainer.parentNode) {
      this.previewContainer.parentNode.removeChild(this.previewContainer);
    }
    if (this.errorContainer && this.errorContainer.parentNode) {
      this.errorContainer.parentNode.removeChild(this.errorContainer);
    }
    if (this.originalContainer) {
      this.showOriginalElements();
    }
    this.customContainer = null;
    this.previewContainer = null;
    this.errorContainer = null;
    this.selectedFiles = [];
  }

  /**
   * Check if handler is properly initialized
   * @returns {boolean}
   */
  isReady() {
    return this.isInitialized;
  }

  /**
   * Handle WPCF7 form submission success
   */
  onFormSuccess() {
    setTimeout(() => {
      this.reset();
    }, 100);
  }

  /**
   * Clear singleton instance
   */
  static clearInstance() {
    if (FileUpload.instance) {
      FileUpload.instance.cleanup();
      FileUpload.instance = null;
    }
  }

  /**
   * Destroy the handler
   */
  destroy() {
    this.cleanup();
    this.isInitialized = false;
    console.log("Enhanced File Upload destroyed");
  }
}

/***/ }),

/***/ "./src/js/utils/api.js":
/*!*****************************!*\
  !*** ./src/js/utils/api.js ***!
  \*****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   api: () => (/* binding */ api)
/* harmony export */ });
/**
 * API utility functions
 * Modern fetch-based AJAX helpers
 */

const api = {
  /**
   * Make GET request
   * @param {string} url - Request URL
   * @param {Object} options - Request options
   * @returns {Promise}
   */
  async get(url, options = {}) {
    return this.request(url, {
      method: 'GET',
      ...options
    });
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
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    };
    const config = {
      ...defaults,
      ...options
    };
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
   * WordPress AJAX helper
   * @param {string} action - WordPress AJAX action
   * @param {Object} data - Request data
   * @param {Object} options - Request options
   * @returns {Promise}
   */
  async wpAjax(action, data = {}, options = {}) {
    const url = window.wpAjax?.ajaxurl || '/wp-admin/admin-ajax.php';
    const nonce = window.wpAjax?.nonce || '';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('nonce', nonce);
    Object.keys(data).forEach(key => {
      formData.append(key, data[key]);
    });
    return this.request(url, {
      method: 'POST',
      body: formData,
      ...options
    });
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
    const errorMessages = {
      ...defaultMessages,
      ...messages
    };
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
    return Promise.race([request, new Promise((_, reject) => setTimeout(() => reject(new Error('Request timeout')), timeout))]);
  }
};

/***/ }),

/***/ "./src/js/utils/validation.js":
/*!************************************!*\
  !*** ./src/js/utils/validation.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   validation: () => (/* binding */ validation)
/* harmony export */ });
/**
 * Form validation utilities
 * Czech-specific validation rules
 */

const validation = {
  /**
   * Validate Czech IČO (company registration number)
   * @param {string} ico - IČO to validate
   * @returns {Object} Validation result
   */
  ico(ico) {
    const cleaned = ico.replace(/\s/g, '');
    if (!/^\d{8}$/.test(cleaned)) {
      return {
        valid: false,
        error: 'IČO musí mít 8 číslic'
      };
    }

    // Czech IČO checksum validation
    const digits = cleaned.split('').map(Number);
    const weights = [8, 7, 6, 5, 4, 3, 2];
    const sum = digits.slice(0, 7).reduce((acc, digit, index) => acc + digit * weights[index], 0);
    const remainder = sum % 11;
    const checksum = remainder < 2 ? remainder : 11 - remainder;
    if (digits[7] !== checksum) {
      return {
        valid: false,
        error: 'Neplatné IČO (chybný kontrolní součet)'
      };
    }
    return {
      valid: true
    };
  },
  /**
   * Validate email address
   * @param {string} email - Email to validate
   * @returns {Object} Validation result
   */
  email(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return {
        valid: false,
        error: 'Neplatný formát e-mailové adresy'
      };
    }
    return {
      valid: true
    };
  },
  /**
   * Validate Czech phone number
   * @param {string} phone - Phone to validate
   * @returns {Object} Validation result
   */
  phone(phone) {
    const cleaned = phone.replace(/[\s\-\(\)]/g, '');
    const phoneRegex = /^(\+420)?[0-9]{9}$/;
    if (!phoneRegex.test(cleaned)) {
      return {
        valid: false,
        error: 'Neplatný formát telefonního čísla'
      };
    }
    return {
      valid: true
    };
  },
  /**
   * Validate required field
   * @param {string} value - Value to validate
   * @param {string} fieldName - Field name for error message
   * @returns {Object} Validation result
   */
  required(value, fieldName = 'Pole') {
    if (!value || value.trim() === '') {
      return {
        valid: false,
        error: `${fieldName} je povinné`
      };
    }
    return {
      valid: true
    };
  },
  /**
   * Validate file type
   * @param {File} file - File to validate
   * @param {Array} allowedTypes - Allowed MIME types
   * @returns {Object} Validation result
   */
  fileType(file, allowedTypes = ['image/jpeg', 'image/jpg', 'image/png']) {
    if (!allowedTypes.includes(file.type)) {
      const allowedExtensions = allowedTypes.map(type => {
        switch (type) {
          case 'image/jpeg':
            return 'JPG';
          case 'image/jpg':
            return 'JPG';
          case 'image/png':
            return 'PNG';
          default:
            return type;
        }
      });
      return {
        valid: false,
        error: `Pouze soubory typu ${allowedExtensions.join(', ')} jsou povoleny`
      };
    }
    return {
      valid: true
    };
  },
  /**
   * Validate file size
   * @param {File} file - File to validate
   * @param {number} maxSize - Maximum size in bytes
   * @returns {Object} Validation result
   */
  fileSize(file, maxSize = 5 * 1024 * 1024) {
    // 5MB default
    if (file.size > maxSize) {
      const maxSizeMB = Math.round(maxSize / (1024 * 1024));
      return {
        valid: false,
        error: `Soubor je příliš velký (maximum ${maxSizeMB}MB)`
      };
    }
    return {
      valid: true
    };
  },
  /**
   * Format file size for display
   * @param {number} bytes - File size in bytes
   * @returns {string} Formatted size string
   */
  formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  },
  /**
   * Validate multiple rules for a single value
   * @param {any} value - Value to validate
   * @param {Array} rules - Array of validation rules
   * @returns {Object} Validation result
   */
  validate(value, rules) {
    for (const rule of rules) {
      const result = rule(value);
      if (!result.valid) {
        return result;
      }
    }
    return {
      valid: true
    };
  }
};

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!************************!*\
  !*** ./src/js/main.js ***!
  \************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _modules_ares_handler_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./modules/ares-handler.js */ "./src/js/modules/ares-handler.js");
/* harmony import */ var _modules_file_upload_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./modules/file-upload.js */ "./src/js/modules/file-upload.js");
/**
 * Main JavaScript Entry Point
 * Imports and initializes all modules for the theme
 */




/**
 * Theme Application Class
 * Manages initialization and lifecycle of all modules
 */
class ThemeApp {
  constructor() {
    this.modules = {
      aresHandler: null,
      fileUpload: null
    };
    this.isInitialized = false;
    this.init();
  }

  /**
   * Initialize the application
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
    this.bindGlobalEvents();
    this.isInitialized = true;
    console.log('Theme application initialized successfully');
  }

  /**
   * Initialize all modules
   */
  initializeModules() {
    // Initialize ARES handler for company data lookup
    if (document.querySelector('.registration-form')) {
      try {
        this.modules.aresHandler = new _modules_ares_handler_js__WEBPACK_IMPORTED_MODULE_0__.AresHandler();
        console.log('ARES handler initialized');
      } catch (error) {
        console.error('Failed to initialize ARES handler:', error);
      }
    }

    // Initialize enhanced file upload
    if (document.querySelector('.registration-form input[name="fotky-realizace"]')) {
      try {
        this.modules.fileUpload = new _modules_file_upload_js__WEBPACK_IMPORTED_MODULE_1__.FileUpload();
        console.log('File upload handler initialized');
      } catch (error) {
        console.error('Failed to initialize file upload handler:', error);
      }
    }
  }

  /**
   * Bind global event listeners
   */
  bindGlobalEvents() {
    // Handle Contact Form 7 submission success
    if (typeof window.wpcf7 !== 'undefined') {
      document.addEventListener('wpcf7mailsent', event => {
        this.handleFormSuccess(event);
      });
      document.addEventListener('wpcf7invalid', event => {
        this.handleFormError(event);
      });
      document.addEventListener('wpcf7spam', event => {
        this.handleFormSpam(event);
      });
      document.addEventListener('wpcf7mailfailed', event => {
        this.handleFormFailed(event);
      });
    }

    // Handle dynamic content loading (if needed for AJAX)
    document.addEventListener('DOMContentLoaded', () => {
      this.reinitializeOnDynamicContent();
    });
  }

  /**
   * Handle successful form submission
   * @param {Event} event - WPCF7 event
   */
  handleFormSuccess(event) {
    console.log('Form submitted successfully');

    // Reset file upload interface
    if (this.modules.fileUpload && this.modules.fileUpload.isReady()) {
      this.modules.fileUpload.onFormSuccess();
    }

    // You can add more success handling here
    // e.g., analytics tracking, user notifications, etc.
  }

  /**
   * Handle form validation errors
   * @param {Event} event - WPCF7 event
   */
  handleFormError(event) {
    console.log('Form validation failed');
    // Add custom error handling if needed
  }

  /**
   * Handle spam detection
   * @param {Event} event - WPCF7 event
   */
  handleFormSpam(event) {
    console.log('Form marked as spam');
    // Add custom spam handling if needed
  }

  /**
   * Handle mail sending failure
   * @param {Event} event - WPCF7 event
   */
  handleFormFailed(event) {
    console.log('Form mail sending failed');
    // Add custom failure handling if needed
  }

  /**
   * Reinitialize modules when content is dynamically loaded
   * Useful for AJAX-loaded content
   */
  reinitializeOnDynamicContent() {
    // Observer for dynamically added content
    const observer = new MutationObserver(mutations => {
      mutations.forEach(mutation => {
        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
          // Check if any added nodes contain forms that need our modules
          const hasRegistrationForm = Array.from(mutation.addedNodes).some(node => {
            return node.nodeType === Node.ELEMENT_NODE && (node.matches('.registration-form') || node.querySelector('.registration-form'));
          });
          if (hasRegistrationForm) {
            console.log('Dynamic content detected, reinitializing modules');
            this.reinitializeModules();
          }
        }
      });
    });

    // Start observing
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  /**
   * Reinitialize modules (useful for dynamic content)
   */
  reinitializeModules() {
    // Clean up existing modules
    this.destroyModules();

    // Reinitialize
    this.initializeModules();
  }

  /**
   * Destroy all modules
   */
  destroyModules() {
    Object.keys(this.modules).forEach(key => {
      if (this.modules[key] && typeof this.modules[key].destroy === 'function') {
        this.modules[key].destroy();
      }
      this.modules[key] = null;
    });

    // Clear singleton instances
    _modules_file_upload_js__WEBPACK_IMPORTED_MODULE_1__.FileUpload.clearInstance();
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
    this.destroyModules();
    this.isInitialized = false;
    console.log('Theme application destroyed');
  }
}

// Initialize the application
const app = new ThemeApp();

// Make app available globally for debugging and external access
window.ThemeApp = app;

// For development debugging
if (true) {
  window.ThemeAppDebug = {
    app,
    modules: app.modules
    // Add other utilities for debugging
  };
}
})();

/******/ })()
;
//# sourceMappingURL=main.js.map