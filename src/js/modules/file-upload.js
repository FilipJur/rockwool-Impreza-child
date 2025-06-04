/**
 * Enhanced File Upload Handler
 * Provides drag & drop functionality for Contact Form 7 file uploads
 */

import { dom } from '../utils/dom.js';
import { validation } from '../utils/validation.js';

export class FileUpload {
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
      maxFileSize: 5 * 1024 * 1024, // 5MB
      allowedTypes: ['image/jpeg', 'image/jpg', 'image/png'],
      allowedExtensions: ['.jpg', '.jpeg', '.png']
    };
    this.isInitialized = false;

    FileUpload.instance = this;
    this.init();
  }

  /**
   * Initialize the file upload handler
   */
  init() {
    dom.ready(() => {
      this.setup();
    });
  }

  /**
   * Set up the enhanced file upload interface
   */
  setup() {
    this.fileInput = dom.findOne('.registration-form input[name="fotky-realizace"]');

    if (!this.fileInput) {
      console.warn('File input not found');
      return;
    }

    this.originalContainer = this.fileInput.closest('.wpcf7-form-control-wrap');

    if (!this.originalContainer) {
      console.warn('File input container not found');
      return;
    }

    this.cleanup();
    this.createCustomInterface();
    this.bindEvents();
    this.syncWithOriginalInput();
    this.isInitialized = true;
    
    console.log('Enhanced File Upload initialized successfully');
  }

  /**
   * Create the custom drag & drop interface
   */
  createCustomInterface() {
    // Create upload zone
    this.customContainer = document.createElement('div');
    this.customContainer.className = 'file-upload-zone';

    // File icon SVG
    const iconSvg = this.createFileIcon();
    
    const uploadText = document.createElement('div');
    uploadText.className = 'file-upload-text';
    uploadText.textContent = 'Přetáhněte fotky sem nebo\\nklikněte pro nahrání';

    this.customContainer.innerHTML = iconSvg;
    this.customContainer.appendChild(uploadText);

    // Create preview container
    this.previewContainer = document.createElement('div');
    this.previewContainer.className = 'file-preview-container';

    const previewList = document.createElement('div');
    previewList.className = 'file-preview-list';
    this.previewContainer.appendChild(previewList);

    // Create error container
    this.errorContainer = document.createElement('div');
    this.errorContainer.className = 'file-upload-error';

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
    if (br && br.tagName === 'BR') {
      br.style.display = 'none';
      const small = br.nextElementSibling;
      if (small && small.tagName === 'SMALL') {
        small.style.display = 'none';
      }
    }
  }

  /**
   * Show original form elements
   */
  showOriginalElements() {
    if (!this.originalContainer) return;

    const br = this.originalContainer.nextElementSibling;
    if (br && br.tagName === 'BR') {
      br.style.display = '';
      const small = br.nextElementSibling;
      if (small && small.tagName === 'SMALL') {
        small.style.display = '';
      }
    }
  }

  /**
   * Bind event listeners
   */
  bindEvents() {
    // Click to upload
    dom.on(this.customContainer, 'click', () => {
      this.fileInput.click();
    });

    // File input change
    dom.on(this.fileInput, 'change', (e) => {
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
    dom.on(this.customContainer, 'dragover', (e) => {
      e.preventDefault();
      e.stopPropagation();
      dom.class(this.customContainer, 'drag-over', 'add');
    });

    dom.on(this.customContainer, 'dragleave', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!this.customContainer.contains(e.relatedTarget)) {
        dom.class(this.customContainer, 'drag-over', 'remove');
      }
    });

    dom.on(this.customContainer, 'drop', (e) => {
      e.preventDefault();
      e.stopPropagation();
      dom.class(this.customContainer, 'drag-over', 'remove');

      const files = Array.from(e.dataTransfer.files);
      this.setFilesToInput(files);
    });
  }

  /**
   * Prevent default drag behaviors on document
   */
  preventDefaultDragBehaviors() {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      document.addEventListener(eventName, (e) => {
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
      if (typeof DataTransfer !== 'undefined') {
        const dt = new DataTransfer();
        Array.from(files).forEach(file => {
          dt.items.add(file);
        });
        this.fileInput.files = dt.files;
        this.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
      } else {
        this.showError('Drag and drop není podporován v tomto prohlížeči. Použijte tlačítko pro výběr souborů.');
      }
    } catch (error) {
      console.warn('DataTransfer not supported:', error);
      this.showError('Drag and drop není podporován. Použijte tlačítko pro výběr souborů.');
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
      this.showError(errors.join('<br>'));
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
    const typeValidation = validation.fileType(file, this.config.allowedTypes);
    if (!typeValidation.valid) {
      return typeValidation;
    }

    // File size validation
    const sizeValidation = validation.fileSize(file, this.config.maxFileSize);
    if (!sizeValidation.valid) {
      return sizeValidation;
    }

    return { valid: true };
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
        status: 'selected'
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
    const previewItem = document.createElement('div');
    previewItem.className = 'file-preview-item';
    previewItem.dataset.fileId = fileData.id;

    // Thumbnail
    const thumbnail = document.createElement('div');
    thumbnail.className = 'file-preview-thumbnail';

    if (fileData.file.type.startsWith('image/')) {
      const img = document.createElement('img');
      const reader = new FileReader();
      reader.onload = (e) => {
        img.src = e.target.result;
      };
      reader.readAsDataURL(fileData.file);
      thumbnail.appendChild(img);
    } else {
      thumbnail.innerHTML = '📄';
    }

    // File info
    const info = document.createElement('div');
    info.className = 'file-preview-info';

    const name = document.createElement('div');
    name.className = 'file-preview-name';
    name.textContent = fileData.name;

    const size = document.createElement('div');
    size.className = 'file-preview-size';
    size.textContent = validation.formatFileSize(fileData.size);

    const status = document.createElement('div');
    status.className = 'file-preview-status';
    status.textContent = 'Připraven k nahrání';

    info.appendChild(name);
    info.appendChild(size);
    info.appendChild(status);

    previewItem.appendChild(thumbnail);
    previewItem.appendChild(info);

    const previewList = dom.findOne('.file-preview-list', this.previewContainer);
    previewList.appendChild(previewItem);
  }

  /**
   * Sync with original input changes (e.g., from WPCF7 validation)
   */
  syncWithOriginalInput() {
    const observer = new MutationObserver(() => {
      if (this.fileInput.files && this.fileInput.files.length === 0) {
        this.selectedFiles = [];
        const previewList = dom.findOne('.file-preview-list', this.previewContainer);
        if (previewList) {
          previewList.innerHTML = '';
        }
        this.updatePreviewVisibility();
      }
    });

    observer.observe(this.fileInput, {
      attributes: true,
      attributeFilter: ['value']
    });
  }

  /**
   * Update preview container visibility
   */
  updatePreviewVisibility() {
    if (this.selectedFiles.length > 0) {
      dom.class(this.previewContainer, 'has-files', 'add');
    } else {
      dom.class(this.previewContainer, 'has-files', 'remove');
    }
  }

  /**
   * Show error message
   * @param {string} message - Error message
   */
  showError(message) {
    dom.html(this.errorContainer, message);
    dom.class(this.errorContainer, 'show', 'add');
    dom.class(this.customContainer, 'has-error', 'add');

    setTimeout(() => {
      this.clearErrors();
    }, 5000);
  }

  /**
   * Clear error messages
   */
  clearErrors() {
    dom.class(this.errorContainer, 'show', 'remove');
    dom.class(this.customContainer, 'has-error', 'remove');
  }

  /**
   * Reset the upload interface
   */
  reset() {
    this.selectedFiles = [];
    const previewList = dom.findOne('.file-preview-list', this.previewContainer);
    if (previewList) {
      previewList.innerHTML = '';
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
    console.log('Enhanced File Upload destroyed');
  }
}