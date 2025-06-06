/**
 * Preview Manager Module
 * Handles file preview interactions and thumbnails
 */

export class PreviewManager {
	constructor(container) {
		this.container = container;
		this.observer = null;
	}

	/**
	 * Start observing preview items
	 * @param {Function} onItemAdded - Callback when item is added
	 */
	startObserving(onItemAdded) {
		if (!this.container) return;

		this.observer = new MutationObserver((mutationsList) => {
			for (const mutation of mutationsList) {
				if (mutation.type === 'childList') {
					mutation.addedNodes.forEach(node => {
						if (this._isPreviewItem(node)) {
							console.log('[PreviewManager] Preview item added:', node);
							this._enhancePreviewItem(node);
							if (onItemAdded) onItemAdded(node);
						}
					});
				}
			}
		});

		this.observer.observe(this.container, { 
			childList: true, 
			subtree: false 
		});

		console.log("[PreviewManager] Started observing preview items");
	}

	/**
	 * Stop observing
	 */
	stopObserving() {
		if (this.observer) {
			this.observer.disconnect();
			this.observer = null;
		}
	}

	/**
	 * Check if node is a preview item
	 * @private
	 * @param {Node} node 
	 * @returns {boolean}
	 */
	_isPreviewItem(node) {
		return node.nodeType === Node.ELEMENT_NODE && 
			   node.classList && 
			   node.classList.contains('dnd-upload-status');
	}

	/**
	 * Enhance a preview item
	 * @private
	 * @param {HTMLElement} item 
	 */
	_enhancePreviewItem(item) {
		item.classList.add('file-preview-item-enhanced', 'slide-in');
		this._makeClickable(item);
		this._enhanceThumbnail(item);
	}

	/**
	 * Make preview item clickable for removal
	 * @private
	 * @param {HTMLElement} item 
	 */
	_makeClickable(item) {
		item.addEventListener('click', (e) => {
			// Don't trigger if clicking directly on remove button or its children
			const removeButton = e.target.closest('a[class*="remove"]');
			if (removeButton) {
				// Let the original button handle its own click
				return;
			}

			// Prevent any default behavior and stop propagation
			e.preventDefault();
			e.stopPropagation();

			console.log("[PreviewManager] Preview clicked, attempting removal");

			// Find and programmatically trigger the remove button
			const targetRemoveButton = this._findRemoveButton(item);
			if (targetRemoveButton) {
				console.log("[PreviewManager] Found remove button:", targetRemoveButton);
				
				// Prevent the href="#" from scrolling
				targetRemoveButton.addEventListener('click', (removeEvent) => {
					removeEvent.preventDefault();
				}, { once: true });
				
				// Now trigger the click
				targetRemoveButton.click();
			} else {
				console.warn("[PreviewManager] No remove button found");
			}
		});

		item.style.cursor = 'pointer';
		item.title = 'Click to remove file';
	}

	/**
	 * Find the remove button in various ways
	 * @private
	 * @param {HTMLElement} item 
	 * @returns {HTMLElement|null}
	 */
	_findRemoveButton(item) {
		// Try multiple selectors
		const selectors = [
			'a.remove-file',
			'a[title="Remove"]',
			'a[class*="remove"]',
			'.dnd-icon-remove',
			'[data-storage*="remove"]'
		];

		for (const selector of selectors) {
			const element = item.querySelector(selector);
			if (element) {
				// If it's not an anchor, find the closest anchor
				return element.tagName === 'A' ? element : element.closest('a');
			}
		}

		return null;
	}

	/**
	 * Trigger file removal
	 * @private
	 * @param {HTMLElement} removeButton 
	 */
	_triggerRemoval(removeButton) {
		console.log("[PreviewManager] Attempting to trigger removal on:", removeButton);
		
		// First try - look for data attributes that might contain removal logic
		const storage = removeButton.getAttribute('data-storage');
		if (storage) {
			console.log("[PreviewManager] Found data-storage:", storage);
		}

		// Try to trigger the actual plugin removal logic
		// Create a more complete click event
		const clickEvent = new MouseEvent('click', {
			bubbles: true,
			cancelable: false, // Don't allow preventDefault
			view: window,
			detail: 1,
			button: 0,
			buttons: 1
		});

		// Stop any default behavior on our preview
		removeButton.addEventListener('click', (e) => {
			e.stopPropagation();
		}, { once: true });

		// Dispatch the click event
		const result = removeButton.dispatchEvent(clickEvent);
		console.log("[PreviewManager] Click event dispatched, result:", result);

		// Alternative: Try calling any attached event handlers directly
		if (removeButton._events || removeButton.__eventListeners) {
			console.log("[PreviewManager] Found attached events:", removeButton._events || removeButton.__eventListeners);
		}

		// If href is # but has click handlers, try simulating without href navigation
		if (removeButton.href === window.location.href + '#') {
			// This is likely handled by JavaScript, try triggering without navigation
			setTimeout(() => {
				// Give time for any async operations
				console.log("[PreviewManager] Checking if item was removed...");
			}, 100);
		}
	}

	/**
	 * Enhance thumbnail display
	 * @private
	 * @param {HTMLElement} item 
	 */
	_enhanceThumbnail(item) {
		const imageContainer = item.querySelector('.dnd-upload-image');
		if (!imageContainer) return;

		// Check if plugin generated a thumbnail
		const existingImg = imageContainer.querySelector('img');
		if (existingImg && existingImg.src && existingImg.src !== '') {
			console.log("[PreviewManager] Thumbnail found:", existingImg.src);
			existingImg.style.display = 'block';
			existingImg.style.zIndex = '2';
			return;
		}

		// Plugin doesn't generate thumbnails, try to create our own for images
		const fileName = this._getFileName(item);
		if (fileName && this._isImageFile(fileName)) {
			console.log("[PreviewManager] Attempting to create thumbnail for image:", fileName);
			this._createImageThumbnail(item, imageContainer);
		} else {
			console.log("[PreviewManager] No thumbnail, using fallback icon for:", fileName || 'unknown file');
		}
	}

	/**
	 * Get filename from preview item
	 * @private
	 * @param {HTMLElement} item 
	 * @returns {string|null}
	 */
	_getFileName(item) {
		const nameSpan = item.querySelector('.name span:first-child');
		return nameSpan ? nameSpan.textContent.trim() : null;
	}

	/**
	 * Check if file is an image
	 * @private
	 * @param {string} fileName 
	 * @returns {boolean}
	 */
	_isImageFile(fileName) {
		const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.svg'];
		const extension = fileName.toLowerCase().substring(fileName.lastIndexOf('.'));
		return imageExtensions.includes(extension);
	}

	/**
	 * Create image thumbnail from file input
	 * @private
	 * @param {HTMLElement} item 
	 * @param {HTMLElement} imageContainer 
	 */
	_createImageThumbnail(item, imageContainer) {
		// Try to find the file from the file input
		const hiddenInput = item.querySelector('input[type="hidden"]');
		if (hiddenInput && hiddenInput.value) {
			// The value contains the uploaded file path
			const filePath = hiddenInput.value;
			console.log("[PreviewManager] Found file path:", filePath);
			
			// Create thumbnail image
			const img = document.createElement('img');
			img.src = `/wp-content/uploads/wpcf7_uploads/${filePath}`;
			img.style.width = '100%';
			img.style.height = '100%';
			img.style.objectFit = 'cover';
			img.style.borderRadius = '5px';
			img.style.position = 'relative';
			img.style.zIndex = '1';
			
			img.onload = () => {
				console.log("[PreviewManager] Thumbnail loaded successfully");
				imageContainer.appendChild(img);
			};
			
			img.onerror = () => {
				console.log("[PreviewManager] Thumbnail failed to load, using fallback");
			};
		}
	}
}