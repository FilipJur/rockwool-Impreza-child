/**
 * Enhanced File Upload Handler
 * Provides drag & drop functionality for Contact Form 7 file uploads
 */

import { validation } from "../utils/validation.js";

export class FileUpload {
	constructor() {
		// Singleton pattern
		if (FileUpload.instance) {
			console.log(`[FileUpload] Returning existing singleton instance with ${FileUpload.instance.selectedFiles.length} files`);
			return FileUpload.instance;
		}

		console.log(`[FileUpload] Creating new FileUpload instance`);
		this.fileInput = null;
		this.originalContainer = null;
		this.customContainer = null;
		this.previewContainer = null;
		this.errorContainer = null;
		this.selectedFiles = [];
		this.config = {
			maxFileSize: 5 * 1024 * 1024, // 5MB
			allowedTypes: ["image/jpeg", "image/jpg", "image/png"],
			allowedExtensions: [".jpg", ".jpeg", ".png"],
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
		this.fileInput = document.querySelector(
			'.registration-form input[name="fotky-realizace"]',
		);

		if (!this.fileInput) {
			console.warn("File input not found");
			return;
		}

		this.originalContainer = this.fileInput.closest(
			".wpcf7-form-control-wrap",
		);

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
		uploadText.innerHTML =
			"Přetáhněte fotky sem nebo<br><span>klikněte pro nahrání</span>";

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

		// File input change - store bound function for later removal
		this.boundHandleFileSelection = (e) => {
			this.handleFileSelection(e.target.files);
		};
		this.fileInput.addEventListener("change", this.boundHandleFileSelection);

		// Drag and drop events
		this.bindDragDropEvents();

		// Prevent default drag behaviors globally
		this.preventDefaultDragBehaviors();
	}

	/**
	 * Bind drag and drop event listeners
	 */
	bindDragDropEvents() {
		this.customContainer.addEventListener("dragover", (e) => {
			e.preventDefault();
			e.stopPropagation();
			this.customContainer.classList.add("drag-over");
		});

		this.customContainer.addEventListener("dragleave", (e) => {
			e.preventDefault();
			e.stopPropagation();
			if (!this.customContainer.contains(e.relatedTarget)) {
				this.customContainer.classList.remove("drag-over");
			}
		});

		this.customContainer.addEventListener("drop", (e) => {
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
		["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
			document.addEventListener(
				eventName,
				(e) => {
					e.preventDefault();
					e.stopPropagation();
				},
				false,
			);
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
				Array.from(files).forEach((file) => {
					dt.items.add(file);
				});
				this.fileInput.files = dt.files;
				this.fileInput.dispatchEvent(
					new Event("change", { bubbles: true }),
				);
			} else {
				this.showError(
					"Drag and drop není podporován v tomto prohlížeči. Použijte tlačítko pro výběr souborů.",
				);
			}
		} catch (error) {
			console.warn("DataTransfer not supported:", error);
			this.showError(
				"Drag and drop není podporován. Použijte tlačítko pro výběr souborů.",
			);
		}
	}

	/**
	 * Handle file selection (both click and drag & drop)
	 * @param {FileList} files - Selected files
	 */
	handleFileSelection(files) {
		console.log(`[FileUpload] handleFileSelection called with ${files ? files.length : 0} files`);
		console.log(`[FileUpload] Current selectedFiles before handling: ${this.selectedFiles.length}`);
		console.log(`[FileUpload] Current selectedFiles names:`, this.selectedFiles.map(f => f.name));
		console.log(`[FileUpload] FileInput files count:`, this.fileInput.files ? this.fileInput.files.length : 0);
		
		this.clearErrors();

		// Don't clear if files is empty but we have existing files (user cancelled dialog)
		if (!files || files.length === 0) {
			if (this.selectedFiles.length === 0) {
				console.log(`[FileUpload] No files and no existing files, clearing all`);
				const previewList = this.previewContainer.querySelector(".file-preview-list");
				if (previewList) {
					previewList.innerHTML = "";
					console.log(`[FileUpload] Cleared DOM preview list`);
				}
				this.updatePreviewVisibility();
			} else {
				console.log(`[FileUpload] No new files but keeping existing ${this.selectedFiles.length} files (user cancelled dialog)`);
			}
			return;
		}

		const validFiles = [];
		const errors = [];
		const existingFileNames = this.selectedFiles.map(f => f.name);

		Array.from(files).forEach((file) => {
			// Check for duplicates
			if (existingFileNames.includes(file.name)) {
				errors.push(`${file.name}: Soubor již byl vybrán`);
				return;
			}

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
			console.log(`[FileUpload] Adding ${validFiles.length} new valid files to existing ${this.selectedFiles.length}`);
			this.addFiles(validFiles);
			// Update the file input to include all files (existing + new)
			this.updateFileInput();
		}

		console.log(`[FileUpload] Final selectedFiles count: ${this.selectedFiles.length}`);
		this.updatePreviewVisibility();
	}

	/**
	 * Validate a single file
	 * @param {File} file - File to validate
	 * @returns {Object} Validation result
	 */
	validateFile(file) {
		// File type validation
		const typeValidation = validation.fileType(
			file,
			this.config.allowedTypes,
		);
		if (!typeValidation.valid) {
			return typeValidation;
		}

		// File size validation
		const sizeValidation = validation.fileSize(
			file,
			this.config.maxFileSize,
		);
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
		files.forEach((file) => {
			const fileData = {
				file: file,
				id: Date.now() + Math.random(),
				name: file.name,
				size: file.size,
				status: "selected",
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
		console.log(`[FileUpload] Creating preview for file:`, { id: fileData.id, name: fileData.name });
		
		const previewItem = document.createElement("div");
		previewItem.className = "file-preview-item";
		previewItem.dataset.fileId = fileData.id;

		// Thumbnail
		const thumbnail = document.createElement("div");
		thumbnail.className = "file-preview-thumbnail";

		if (fileData.file.type.startsWith("image/")) {
			const img = document.createElement("img");
			const reader = new FileReader();
			reader.onload = (e) => {
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
		size.textContent = validation.formatFileSize(fileData.size);

		const status = document.createElement("div");
		status.className = "file-preview-status";
		status.textContent = "Připraven k nahrání";

		info.appendChild(name);
		info.appendChild(size);
		info.appendChild(status);

		// Remove button
		const removeButton = document.createElement("button");
		removeButton.type = "button";
		removeButton.className = "file-preview-remove";
		removeButton.innerHTML = "×";
		removeButton.setAttribute("aria-label", "Odstranit soubor");
		removeButton.addEventListener("click", (e) => {
			e.preventDefault();
			e.stopPropagation();
			this.removeFile(fileData.id);
		});

		previewItem.appendChild(thumbnail);
		previewItem.appendChild(info);
		previewItem.appendChild(removeButton);

		const previewList =
			this.previewContainer.querySelector(".file-preview-list");
		previewList.appendChild(previewItem);
		
		console.log(`[FileUpload] Preview created and added to DOM for file ID: ${fileData.id}`);
		console.log(`[FileUpload] Total preview items in DOM: ${previewList.children.length}`);
	}

	/**
	 * Sync with original input changes (e.g., from WPCF7 validation)
	 */
	syncWithOriginalInput() {
		const observer = new MutationObserver(() => {
			if (this.fileInput.files && this.fileInput.files.length === 0) {
				this.selectedFiles = [];
				const previewList =
					this.previewContainer.querySelector(".file-preview-list");
				if (previewList) {
					previewList.innerHTML = "";
				}
				this.updatePreviewVisibility();
			}
		});

		observer.observe(this.fileInput, {
			attributes: true,
			attributeFilter: ["value"],
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
		const previewList =
			this.previewContainer.querySelector(".file-preview-list");
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
	 * Remove a specific file from selection
	 * @param {string} fileId - ID of file to remove
	 */
	removeFile(fileId) {
		console.log(`[FileUpload] Removing file with ID: ${fileId}`);
		console.log(`[FileUpload] Current selectedFiles count: ${this.selectedFiles.length}`);
		console.log(`[FileUpload] Current selectedFiles:`, this.selectedFiles.map(f => ({ id: f.id, name: f.name })));

		// Find file before removal for debugging
		const fileToRemove = this.selectedFiles.find(fileData => fileData.id === fileId);
		if (!fileToRemove) {
			console.warn(`[FileUpload] File with ID ${fileId} not found in selectedFiles array`);
			return;
		}
		console.log(`[FileUpload] Found file to remove:`, { id: fileToRemove.id, name: fileToRemove.name });

		// Remove from selectedFiles array
		const originalLength = this.selectedFiles.length;
		this.selectedFiles = this.selectedFiles.filter(fileData => fileData.id !== fileId);
		console.log(`[FileUpload] Filtered selectedFiles: ${originalLength} -> ${this.selectedFiles.length}`);

		// Remove preview element
		const previewItem = this.previewContainer.querySelector(`[data-file-id="${fileId}"]`);
		if (previewItem) {
			console.log(`[FileUpload] Removing preview element for file ID: ${fileId}`);
			previewItem.remove();
		} else {
			console.warn(`[FileUpload] Preview element not found for file ID: ${fileId}`);
		}

		// Verify preview elements count matches selectedFiles count
		const remainingPreviews = this.previewContainer.querySelectorAll('.file-preview-item');
		console.log(`[FileUpload] Remaining preview elements: ${remainingPreviews.length}, selectedFiles: ${this.selectedFiles.length}`);

		// Update file input with remaining files
		this.updateFileInput();
		this.updatePreviewVisibility();

		console.log(`[FileUpload] File removal completed for ID: ${fileId}`);
	}

	/**
	 * Update the file input with current selected files
	 */
	updateFileInput() {
		console.log(`[FileUpload] Updating file input with ${this.selectedFiles.length} files`);
		
		try {
			if (typeof DataTransfer !== "undefined") {
				const dt = new DataTransfer();
				this.selectedFiles.forEach((fileData, index) => {
					console.log(`[FileUpload] Adding file ${index + 1}:`, fileData.name);
					dt.items.add(fileData.file);
				});
				
				const previousFileCount = this.fileInput.files ? this.fileInput.files.length : 0;
				
				// Temporarily remove event listener to prevent recursive calls
				this.fileInput.removeEventListener("change", this.boundHandleFileSelection);
				
				this.fileInput.files = dt.files;
				console.log(`[FileUpload] File input updated: ${previousFileCount} -> ${this.fileInput.files.length} files`);
				
				// Re-add event listener after a brief delay
				setTimeout(() => {
					this.fileInput.addEventListener("change", this.boundHandleFileSelection);
				}, 0);
				
				console.log(`[FileUpload] File input updated without triggering change event`);
			} else {
				console.warn("[FileUpload] DataTransfer not supported");
			}
		} catch (error) {
			console.error("[FileUpload] Could not update file input:", error);
		}
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
