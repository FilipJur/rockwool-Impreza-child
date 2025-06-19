/**
 * Enhanced File Upload Handler
 * Provides drag & drop functionality for Contact Form 7 file uploads
 */

import { FILE_UPLOAD_CONFIG } from "./constants.js";
import { setupPreviewObserver } from "./preview-manager.js";
import { applyStaggeredAnimation } from "./animation-manager.js";

/**
 * Setup enhanced file upload functionality
 * @param {string|HTMLElement} selector - CSS selector or element for file upload container
 * @param {Object} options - Configuration options
 * @returns {Object|null} Handler object with methods, or null if setup fails
 */
export function setupFileUpload(selector = '.codedropz-upload-wrapper', options = {}) {
	// Find the container element
	let container;
	if (typeof selector === 'string') {
		container = document.querySelector(selector);
	} else if (selector instanceof HTMLElement) {
		container = selector;
	} else {
		console.warn('[FileUpload] Invalid selector provided');
		return null;
	}

	if (!container) {
		console.warn(`[FileUpload] Container "${selector}" not found.`);
		return null;
	}

	// Merge configuration
	const config = { ...FILE_UPLOAD_CONFIG, ...options };
	
	// State variables
	let fileInput = null;
	let originalContainer = null;
	let pluginDropZoneHandler = null;
	let isInitialized = false;
	let observer = null;
	let previewObserver = null;

	// Event listeners for cleanup
	const eventListeners = [];

	/**
	 * Initialize the file upload handler
	 */
	function init() {
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", setup);
		} else {
			setup();
		}
	}

	/**
	 * Set up the enhanced file upload interface
	 */
	function setup() {
		// Start with the provided container, then find the file input within it
		originalContainer = container;
		
		// Try to find file input within the container
		fileInput = container.querySelector('input[type="file"]');
		
		// Fallback selectors for different plugin configurations
		if (!fileInput) {
			const fallbackSelectors = [
				'#realizace-upload',
				'input[data-name="mfile-747"]',
				'.wpcf7-drag-n-drop-file'
			];
			
			for (const sel of fallbackSelectors) {
				fileInput = container.querySelector(sel) || document.querySelector(sel);
				if (fileInput) break;
			}
		}

		if (!fileInput) {
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

		console.log("[FileUpload] Found plugin input:", fileInput.outerHTML);

		// Ensure we have the correct container
		const foundContainer = fileInput.closest(".codedropz-upload-wrapper");
		if (foundContainer) {
			originalContainer = foundContainer;
		}

		console.log("[FileUpload] Plugin wrapper found:", originalContainer);

		if (!originalContainer) {
			console.warn("[FileUpload] Plugin wrapper .codedropz-upload-wrapper not found. Cannot enhance UI.");
			return;
		}

		pluginDropZoneHandler = originalContainer.querySelector('.codedropz-upload-handler');
		if (!pluginDropZoneHandler) {
			console.warn("[FileUpload] Plugin drop zone handler .codedropz-upload-handler not found. Cannot enhance UI.");
			return;
		}

		enhancePluginUI();
		setupPreviewManager();
		setupClickableZone();

		isInitialized = true;
		console.log("Enhanced File Upload (Plugin UI Mode) initialized successfully");
	}

	/**
	 * Create file icon element
	 * @returns {string} Image element markup
	 */
	function createFileIcon() {
		return `<img class="file-upload-icon" src="${config.iconPath}" width="32" height="32" alt="File upload icon">`;
	}

	/**
	 * Modify the plugin's static UI elements (text, inject icon).
	 */
	function enhancePluginUI() {
		if (!pluginDropZoneHandler) {
			console.warn("[FileUpload] enhancePluginUI: pluginDropZoneHandler not found.");
			return;
		}

		const innerZone = pluginDropZoneHandler.querySelector('.codedropz-upload-inner');
		if (innerZone) {
			// Ensure icon is not duplicated if this method is somehow called multiple times
			if (!innerZone.querySelector('.file-upload-icon')) {
				const iconSvg = createFileIcon();
				innerZone.insertAdjacentHTML('afterbegin', iconSvg);
			}

			// Modify Texts
			const h3 = innerZone.querySelector('h3');
			if (h3) {
				h3.innerHTML = config.texts.dragDrop;
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
				orSpan.innerHTML = config.texts.or;
			} else {
				console.warn("[FileUpload] enhancePluginUI: 'or' span not found or not uniquely identifiable in .codedropz-upload-inner.");
			}

			const browseLink = innerZone.querySelector('a.cd-upload-btn');
			if (browseLink) {
				browseLink.textContent = "";
				const spanElement = document.createElement('span');
				spanElement.textContent = config.texts.browse;
				browseLink.appendChild(spanElement);
			} else {
				console.warn("[FileUpload] enhancePluginUI: Browse link (a.cd-upload-btn) not found in .codedropz-upload-inner.");
			}
		} else {
			console.warn("[FileUpload] enhancePluginUI: .codedropz-upload-inner not found.");
		}

		if (fileInput) {
			fileInput.setAttribute('tabindex', '-1');
			fileInput.style.pointerEvents = 'none';
		}
		console.log("[FileUpload] Plugin UI text and icon enhancement attempted.");
	}

	/**
	 * Setup preview observer for handling file previews
	 */
	function setupPreviewManager() {
		if (!originalContainer) return;

		previewObserver = setupPreviewObserver(originalContainer, (item) => {
			// Apply animations when item is added
			applyStaggeredAnimation(item, originalContainer, config);
		});

		console.log("[FileUpload] Preview observer initialized");
	}

	/**
	 * Setup clickable zone functionality
	 */
	function setupClickableZone() {
		if (!pluginDropZoneHandler || !fileInput) return;

		// Event handlers
		const handleClickableZoneClick = (e) => {
			// Don't trigger if clicking on the browse button directly
			if (e.target.closest('.cd-upload-btn')) return;
			
			// Trigger file input click
			fileInput.click();
			console.log("[FileUpload] Drop zone clicked, opening file dialog");
		};

		const handleDragOver = (e) => {
			e.preventDefault();
			pluginDropZoneHandler.classList.add('drag-over');
		};

		const handleDragLeave = (e) => {
			// Only remove if leaving the handler itself, not child elements
			if (!pluginDropZoneHandler.contains(e.relatedTarget)) {
				pluginDropZoneHandler.classList.remove('drag-over');
			}
		};

		const handleDrop = () => {
			pluginDropZoneHandler.classList.remove('drag-over');
		};

		// Bind events and track for cleanup
		pluginDropZoneHandler.addEventListener('click', handleClickableZoneClick);
		eventListeners.push({ element: pluginDropZoneHandler, event: 'click', handler: handleClickableZoneClick });

		pluginDropZoneHandler.addEventListener('dragover', handleDragOver);
		eventListeners.push({ element: pluginDropZoneHandler, event: 'dragover', handler: handleDragOver });

		pluginDropZoneHandler.addEventListener('dragleave', handleDragLeave);
		eventListeners.push({ element: pluginDropZoneHandler, event: 'dragleave', handler: handleDragLeave });

		pluginDropZoneHandler.addEventListener('drop', handleDrop);
		eventListeners.push({ element: pluginDropZoneHandler, event: 'drop', handler: handleDrop });

		// Add visual feedback for clickable state
		pluginDropZoneHandler.style.cursor = 'pointer';

		console.log("[FileUpload] Clickable zone setup completed");
	}

	/**
	 * Handle WPCF7 form submission success
	 */
	function onFormSuccess() {
		// Plugin handles its own reset on form success
		console.log("[FileUpload] onFormSuccess - relying on plugin for reset.");
	}

	/**
	 * Clean up and remove custom elements
	 */
	function cleanup() {
		// Remove event listeners
		eventListeners.forEach(({ element, event, handler }) => {
			element.removeEventListener(event, handler);
		});
		eventListeners.length = 0;

		// Cleanup preview observer
		if (previewObserver) {
			previewObserver.stopObserving();
			previewObserver = null;
		}
		
		// Cleanup mutation observer
		if (observer) {
			observer.disconnect();
			observer = null;
		}

		isInitialized = false;
		console.log("[FileUpload] Cleanup completed.");
	}

	// Initialize the handler
	init();

	// Return handler object with public methods
	return {
		cleanup,
		isReady: () => isInitialized,
		onFormSuccess,
		enhancePluginUI,
		createFileIcon,
		getContainer: () => originalContainer,
		getFileInput: () => fileInput,
		getConfig: () => config
	};
}
