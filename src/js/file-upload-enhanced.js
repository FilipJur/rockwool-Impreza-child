class EnhancedFileUpload {
  constructor() {
    // Singleton pattern - prevent multiple instances
    if (EnhancedFileUpload.instance) {
      return EnhancedFileUpload.instance;
    }

    this.fileInput = null;
    this.originalContainer = null;
    this.customContainer = null;
    this.selectedFiles = [];
    this.maxFileSize = 5 * 1024 * 1024; // 5MB
    this.allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    this.allowedExtensions = ['.jpg', '.jpeg', '.png'];

    EnhancedFileUpload.instance = this;
    this.init();
  }

  init() {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.setup());
    } else {
      this.setup();
    }
  }

  setup() {
    this.fileInput = document.querySelector('.registration-form input[name="fotky-realizace"]');

    if (!this.fileInput) {
      console.warn('File input not found');
      return;
    }

    this.originalContainer = this.fileInput.closest('.wpcf7-form-control-wrap');

    if (!this.originalContainer) {
      console.warn('File input container not found');
      return;
    }

    // Clean up any existing custom interface first
    this.cleanup();

    this.createCustomInterface();
    this.bindEvents();
    this.syncWithOriginalInput();
  }

  createCustomInterface() {
    // Create custom upload zone
    this.customContainer = document.createElement('div');
    this.customContainer.className = 'file-upload-zone';

    // Get the SVG icon content from the existing file.svg
    const iconSvg = `<svg class="file-upload-icon" width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M3.55556 32C2.57778 32 1.74104 31.6521 1.04533 30.9564C0.34963 30.2607 0.00118519 29.4234 0 28.4444V3.55556C0 2.57778 0.348445 1.74104 1.04533 1.04533C1.74222 0.34963 2.57896 0.00118519 3.55556 0H28.4444C29.4222 0 30.2596 0.348445 30.9564 1.04533C31.6533 1.74222 32.0012 2.57896 32 3.55556V28.4444C32 29.4222 31.6521 30.2596 30.9564 30.9564C30.2607 31.6533 29.4234 32.0012 28.4444 32H3.55556ZM3.55556 28.4444H28.4444V3.55556H3.55556V28.4444ZM5.33333 24.8889H26.6667L20 16L14.6667 23.1111L10.6667 17.7778L5.33333 24.8889Z" fill="#CCCCCC"/>
    </svg>`;

    const uploadText = document.createElement('div');
    uploadText.className = 'file-upload-text';
    uploadText.textContent = 'Přetáhněte fotky sem nebo\nklikněte pro nahrání';

    this.customContainer.innerHTML = iconSvg;
    this.customContainer.appendChild(uploadText);

    // Create preview container
    this.previewContainer = document.createElement('div');
    this.previewContainer.className = 'file-preview-container';

    const previewList = document.createElement('div');
    previewList.className = 'file-preview-list';
    this.previewContainer.appendChild(previewList);

    // Create error message container
    this.errorContainer = document.createElement('div');
    this.errorContainer.className = 'file-upload-error';

    // Insert custom interface before the original container
    this.originalContainer.parentNode.insertBefore(this.customContainer, this.originalContainer);
    this.originalContainer.parentNode.insertBefore(this.previewContainer, this.originalContainer);
    this.originalContainer.parentNode.insertBefore(this.errorContainer, this.originalContainer);

    // Hide the original interface elements
    this.hideOriginalElements();
  }

  hideOriginalElements() {
    // Hide the original elements
    const br = this.originalContainer.nextElementSibling;
    if (br && br.tagName === 'BR') {
      br.style.display = 'none';
      const small = br.nextElementSibling;
      if (small && small.tagName === 'SMALL') {
        small.style.display = 'none';
      }
    }
  }

  bindEvents() {
    // Click to upload
    this.customContainer.addEventListener('click', () => {
      this.fileInput.click();
    });

    // File input change event - let WPCF7 handle validation, we just update preview
    this.fileInput.addEventListener('change', (e) => {
      this.handleFileSelection(e.target.files);
    });

    // Drag and drop events
    this.customContainer.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.customContainer.classList.add('drag-over');
    });

    this.customContainer.addEventListener('dragleave', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!this.customContainer.contains(e.relatedTarget)) {
        this.customContainer.classList.remove('drag-over');
      }
    });

    this.customContainer.addEventListener('drop', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.customContainer.classList.remove('drag-over');

      const files = Array.from(e.dataTransfer.files);

      // For drag & drop, we need to manually set the files to the input
      // This is the tricky part - we'll use a more compatible approach
      this.setFilesToInput(files);
    });

    // Prevent default drag behaviors on the document
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      document.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
      }, false);
    });
  }

  setFilesToInput(files) {
    // More compatible way to set files - create a new DataTransfer if supported
    if (files && files.length > 0) {
      try {
        // Check if DataTransfer is supported
        if (typeof DataTransfer !== 'undefined') {
          const dt = new DataTransfer();
          Array.from(files).forEach(file => {
            dt.items.add(file);
          });
          this.fileInput.files = dt.files;

          // Trigger change event for WPCF7
          this.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          // Fallback: show error message
          this.showError('Drag and drop není podporován v tomto prohlížeči. Použijte tlačítko pro výběr souborů.');
        }
      } catch (error) {
        console.warn('DataTransfer not supported:', error);
        this.showError('Drag and drop není podporován. Použijte tlačítko pro výběr souborů.');
      }
    }
  }

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
      const validation = this.validateFile(file);
      if (validation.valid) {
        validFiles.push(file);
      } else {
        errors.push(`${file.name}: ${validation.error}`);
      }
    });

    if (errors.length > 0) {
      this.showError(errors.join('<br>'));
    }

    // Update our preview with valid files (WPCF7 will handle the actual validation)
    if (validFiles.length > 0) {
      this.addFiles(validFiles);
    }

    this.updatePreviewVisibility();
  }

  validateFile(file) {
    // Basic client-side validation (WPCF7 will do server-side validation)
    if (!this.allowedTypes.includes(file.type)) {
      const extension = '.' + file.name.split('.').pop().toLowerCase();
      if (!this.allowedExtensions.includes(extension)) {
        return {
          valid: false,
          error: 'Pouze obrázky (JPG, PNG) jsou povoleny'
        };
      }
    }

    if (file.size > this.maxFileSize) {
      return {
        valid: false,
        error: `Soubor je příliš velký (max ${this.formatFileSize(this.maxFileSize)})`
      };
    }

    return { valid: true };
  }

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

  createFilePreview(fileData) {
    const previewItem = document.createElement('div');
    previewItem.className = 'file-preview-item';
    previewItem.dataset.fileId = fileData.id;

    const thumbnail = document.createElement('div');
    thumbnail.className = 'file-preview-thumbnail';

    // Create thumbnail if it's an image
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

    const info = document.createElement('div');
    info.className = 'file-preview-info';

    const name = document.createElement('div');
    name.className = 'file-preview-name';
    name.textContent = fileData.name;

    const size = document.createElement('div');
    size.className = 'file-preview-size';
    size.textContent = this.formatFileSize(fileData.size);

    const status = document.createElement('div');
    status.className = 'file-preview-status';
    status.textContent = 'Připraven k nahrání';

    info.appendChild(name);
    info.appendChild(size);
    info.appendChild(status);

    // Note: Removing individual file removal since WPCF7 doesn't support it well
    // Users can reselect files to replace them

    previewItem.appendChild(thumbnail);
    previewItem.appendChild(info);

    const previewList = this.previewContainer.querySelector('.file-preview-list');
    previewList.appendChild(previewItem);
  }

  syncWithOriginalInput() {
    // Sync with any changes to the original input (e.g., from WPCF7 validation)
    const observer = new MutationObserver(() => {
      if (this.fileInput.files && this.fileInput.files.length === 0) {
        // Input was cleared, clear our preview too
        this.selectedFiles = [];
        const previewList = this.previewContainer.querySelector('.file-preview-list');
        previewList.innerHTML = '';
        this.updatePreviewVisibility();
      }
    });

    observer.observe(this.fileInput, {
      attributes: true,
      attributeFilter: ['value']
    });
  }

  updatePreviewVisibility() {
    if (this.selectedFiles.length > 0) {
      this.previewContainer.classList.add('has-files');
    } else {
      this.previewContainer.classList.remove('has-files');
    }
  }

  showError(message) {
    this.errorContainer.innerHTML = message;
    this.errorContainer.classList.add('show');
    this.customContainer.classList.add('has-error');

    // Auto-hide error after 5 seconds
    setTimeout(() => {
      this.clearErrors();
    }, 5000);
  }

  clearErrors() {
    this.errorContainer.classList.remove('show');
    this.customContainer.classList.remove('has-error');
  }

  formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  cleanup() {
    // Remove custom elements if they exist
    if (this.customContainer && this.customContainer.parentNode) {
      this.customContainer.parentNode.removeChild(this.customContainer);
    }
    if (this.previewContainer && this.previewContainer.parentNode) {
      this.previewContainer.parentNode.removeChild(this.previewContainer);
    }
    if (this.errorContainer && this.errorContainer.parentNode) {
      this.errorContainer.parentNode.removeChild(this.errorContainer);
    }

    // Show original elements if they were hidden
    if (this.originalContainer) {
      this.showOriginalElements();
    }

    // Reset properties
    this.customContainer = null;
    this.previewContainer = null;
    this.errorContainer = null;
    this.selectedFiles = [];
  }

  showOriginalElements() {
    if (!this.originalContainer) return;

    // Show the original elements that were hidden
    const br = this.originalContainer.nextElementSibling;
    if (br && br.tagName === 'BR') {
      br.style.display = '';
      const small = br.nextElementSibling;
      if (small && small.tagName === 'SMALL') {
        small.style.display = '';
      }
    }
  }

  reset() {
    // Clear selected files and preview
    this.selectedFiles = [];
    if (this.previewContainer) {
      const previewList = this.previewContainer.querySelector('.file-preview-list');
      if (previewList) {
        previewList.innerHTML = '';
      }
      this.updatePreviewVisibility();
    }
    this.clearErrors();
  }

  static clearInstance() {
    if (EnhancedFileUpload.instance) {
      EnhancedFileUpload.instance.cleanup();
      EnhancedFileUpload.instance = null;
    }
  }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new EnhancedFileUpload();
});

// Also handle dynamic form loading (if forms are loaded via AJAX)
if (typeof window.wpcf7 !== 'undefined') {
  document.addEventListener('wpcf7mailsent', () => {
    // Reset file upload after successful submission
    setTimeout(() => {
      const instance = EnhancedFileUpload.instance;
      if (instance) {
        instance.reset();
      } else {
        new EnhancedFileUpload();
      }
    }, 100);
  });
}
