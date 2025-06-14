/**
 * Enhanced File Upload Handler
 * Provides drag & drop functionality for Contact Form 7 file uploads
 */

// Remove unused import
// import { validation } from "../../utils/validation.js";
import { FILE_UPLOAD_CONFIG } from "./constants.js";
import { setupPreviewObserver } from "./preview-manager.js";
import { applyStaggeredAnimation } from "./animation-manager.js";
console.log("FileUpload.js loaded");

export class FileUpload {


	constructor() {
		// Singleton pattern
		if (FileUpload.instance) {
			console.log(`[FileUpload] Returning existing singleton instance with ${FileUpload.instance.selectedFiles.length} files`);
			return FileUpload.instance;
		}

		console.log(`[FileUpload] Creating new FileUpload instance`);
		this.fileInput = null; // The actual <input type="file" id="realizace-upload">
		this.originalContainer = null; // The .codedropz-upload-wrapper
		this.pluginDropZoneHandler = null; // Will be .codedropz-upload-handler
		this.config = FILE_UPLOAD_CONFIG;
		this.isInitialized = false;
		this.observer = null; // For MutationObserver

		// Initialize managers
		this.previewObserver = null;

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
		// Target the plugin input with specific ID, or fallback to plugin selectors
		this.fileInput = document.querySelector('#realizace-upload');
		console.log("[FileUpload] Looking for plugin input with ID 'realizace-upload':", this.fileInput);

		// Fallback to plugin selectors if ID not found
		if (!this.fileInput) {
			this.fileInput = document.querySelector('input[data-name="mfile-747"]');
			console.log("[FileUpload] Fallback to data-name selector:", this.fileInput);
		}

		// Another fallback to any plugin input
		if (!this.fileInput) {
			this.fileInput = document.querySelector('.wpcf7-drag-n-drop-file');
			console.log("[FileUpload] Fallback to class selector:", this.fileInput);
		}

		if (!this.fileInput) {
			console.warn("[FileUpload] No plugin input found - debugging available elements:");
			console.log("[FileUpload] All file inputs:", document.querySelectorAll('input[type="file"]'));
			console.log("[FileUpload] All mfile inputs:", document.querySelectorAll('input[data-name*="mfile"]'));
			console.log("[FileUpload] All plugin inputs:", document.querySelectorAll('.wpcf7-drag-n-drop-file'));
			console.log("[FileUpload] All codedropz wrappers:", document.querySelectorAll('.codedropz-upload-wrapper'));
			console.log("[FileUpload] All wpcf7 wraps:", document.querySelectorAll('.wpcf7-form-control-wrap'));
			console.log("[FileUpload] Page HTML contains 'mfile':", document.documentElement.innerHTML.includes('mfile'));
			console.log("[FileUpload] Page HTML contains 'codedropz':", document.documentElement.innerHTML.includes('codedropz'));
			return;
		}

		console.log("[FileUpload] Found plugin input:", this.fileInput.outerHTML);

		// Get the plugin wrapper (this should always exist for our plugin)
		this.originalContainer = this.fileInput.closest(".codedropz-upload-wrapper");
		this.pluginWrapper = this.originalContainer;
		this.isPluginUpload = true; // We only support plugin now

		console.log("[FileUpload] Plugin wrapper found:", this.originalContainer);

		if (!this.originalContainer) {
			console.warn("[FileUpload] Plugin wrapper .codedropz-upload-wrapper not found. Cannot enhance UI.");
			return;
		}

		this.pluginDropZoneHandler = this.originalContainer.querySelector('.codedropz-upload-handler');
		if (!this.pluginDropZoneHandler) {
			console.warn("[FileUpload] Plugin drop zone handler .codedropz-upload-handler not found. Cannot enhance UI.");
			return;
		}

		this.enhancePluginUI();
		this.setupPreviewManager();
		this.setupClickableZone();

		this.isInitialized = true;
		console.log("Enhanced File Upload (Plugin UI Mode) initialized successfully");
	}

	/**
	 * Create file icon element
	 * @returns {string} Image element markup
	 */
	createFileIcon() {
		return `<img class="file-upload-icon" src="${this.config.iconPath}" width="32" height="32" alt="File upload icon">`;
	}

	/**
	 * Modify the plugin's static UI elements (text, inject icon).
	 */
	enhancePluginUI() {
		if (!this.pluginDropZoneHandler) {
			console.warn("[FileUpload] enhancePluginUI: pluginDropZoneHandler not found.");
			return;
		}

		const innerZone = this.pluginDropZoneHandler.querySelector('.codedropz-upload-inner');
		if (innerZone) {
			// Ensure icon is not duplicated if this method is somehow called multiple times
			if (!innerZone.querySelector('.file-upload-icon')) {
				const iconSvg = this.createFileIcon();
				innerZone.insertAdjacentHTML('afterbegin', iconSvg);
			}

			// Modify Texts
			const h3 = innerZone.querySelector('h3');
			if (h3) {
				h3.innerHTML = this.config.texts.dragDrop;
			} else {
				console.warn("[FileUpload] enhancePluginUI: H3 element not found in .codedropz-upload-inner.");
			}

			// Find the 'or' span. It might not have specific classes.
			// Robustly find it, assuming it's a direct child span or a span within a div.
			let orSpan = null;
			const spansInInnerZone = innerZone.querySelectorAll('span');
			for (let span of spansInInnerZone) {
				if (span.textContent.trim().toLowerCase() === 'or') {
					 // Check if it's a direct child or if its parent is a direct child of innerZone,
					 // to avoid grabbing spans from deeper nested structures if any.
					if(span.parentElement === innerZone || span.parentElement.parentElement === innerZone) {
						orSpan = span;
						break;
					}
				}
			}

			if (orSpan) {
				orSpan.innerHTML = this.config.texts.or;
			} else {
				console.warn("[FileUpload] enhancePluginUI: 'or' span not found or not uniquely identifiable in .codedropz-upload-inner.");
			}

			const browseLink = innerZone.querySelector('a.cd-upload-btn');
			if (browseLink) {
				browseLink.textContent = "";
				const spanElement = document.createElement('span');
				spanElement.textContent = this.config.texts.browse;
				browseLink.appendChild(spanElement);
			} else {
				console.warn("[FileUpload] enhancePluginUI: Browse link (a.cd-upload-btn) not found in .codedropz-upload-inner.");
			}
		} else {
			console.warn("[FileUpload] enhancePluginUI: .codedropz-upload-inner not found.");
		}

		if (this.fileInput) {
			this.fileInput.setAttribute('tabindex', '-1');
			this.fileInput.style.pointerEvents = 'none';
		}
		console.log("[FileUpload] Plugin UI text and icon enhancement attempted.");
	}

	/**
	 * Setup preview observer for handling file previews
	 */
	setupPreviewManager() {
		if (!this.originalContainer) return;

		this.previewObserver = setupPreviewObserver(this.originalContainer, (item) => {
			// Apply animations when item is added
			applyStaggeredAnimation(item, this.originalContainer, this.config);
		});

		console.log("[FileUpload] Preview observer initialized");
	}

	/**
	 * Setup clickable zone functionality
	 */
	setupClickableZone() {
		if (!this.pluginDropZoneHandler || !this.fileInput) return;

		// Make entire drop zone clickable
		this.pluginDropZoneHandler.addEventListener('click', (e) => {
			// Don't trigger if clicking on the browse button directly
			if (e.target.closest('.cd-upload-btn')) return;
			
			// Trigger file input click
			this.fileInput.click();
			console.log("[FileUpload] Drop zone clicked, opening file dialog");
		});

		// Add visual feedback for clickable state
		this.pluginDropZoneHandler.style.cursor = 'pointer';
		
		// Add drag visual feedback
		this.pluginDropZoneHandler.addEventListener('dragover', (e) => {
			e.preventDefault();
			this.pluginDropZoneHandler.classList.add('drag-over');
		});

		this.pluginDropZoneHandler.addEventListener('dragleave', (e) => {
			// Only remove if leaving the handler itself, not child elements
			if (!this.pluginDropZoneHandler.contains(e.relatedTarget)) {
				this.pluginDropZoneHandler.classList.remove('drag-over');
			}
		});

		this.pluginDropZoneHandler.addEventListener('drop', () => {
			this.pluginDropZoneHandler.classList.remove('drag-over');
		});

		console.log("[FileUpload] Clickable zone setup completed");
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
		// Plugin handles its own reset on form success
		console.log("[FileUpload] onFormSuccess - relying on plugin for reset.");
	}

	/**
	 * Clean up and remove custom elements
	 */
	cleanup() {
		if (this.previewObserver) {
			this.previewObserver.stopObserving();
			this.previewObserver = null;
		}
		if (this.observer) {
			this.observer.disconnect();
			this.observer = null;
		}
		console.log("[FileUpload] Cleanup completed.");
	}

	/**
	 * Destroy the handler
	 */
	destroy() {
		this.cleanup();
		this.isInitialized = false;
		console.log("Enhanced File Upload destroyed");
	}

	/**
	 * Clear singleton instance
	 */
	static clearInstance() {
		if (FileUpload.instance) {
			if (typeof FileUpload.instance.destroy === 'function') { // Check if destroy exists
				FileUpload.instance.destroy();
			} else if (typeof FileUpload.instance.cleanup === 'function') { // Fallback to cleanup
				 FileUpload.instance.cleanup();
			}
			FileUpload.instance = null;
			console.log("[FileUpload] Singleton instance cleared.");
		}
	}
}
