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

			// Find and trigger the remove button differently
			const targetRemoveButton = this._findRemoveButton(item);
			if (targetRemoveButton) {
				console.log("[PreviewManager] Found remove button:", targetRemoveButton);
				console.log("[PreviewManager] Button href:", targetRemoveButton.href);
				console.log("[PreviewManager] Button onclick:", targetRemoveButton.onclick);
				console.log("[PreviewManager] Button data attributes:", [...targetRemoveButton.attributes].filter(attr => attr.name.startsWith('data-')));
				
				// Try multiple removal strategies
				this._attemptRemoval(targetRemoveButton, item);
			} else {
				console.warn("[PreviewManager] No remove button found");
			}
		});

		item.style.cursor = 'pointer';
		item.title = 'Click to remove file';
	}

	/**
	 * Try multiple removal strategies
	 * @private
	 * @param {HTMLElement} removeButton 
	 * @param {HTMLElement} item 
	 */
	_attemptRemoval(removeButton, item) {
		// Prevent scrolling by temporarily changing href
		const originalHref = removeButton.href;
		removeButton.href = 'javascript:void(0)';
		
		// Strategy 1: Direct click with preventDefault
		const clickHandler = (e) => {
			e.preventDefault();
			e.stopPropagation();
			console.log("[PreviewManager] Click intercepted, preventing scroll");
		};
		
		// Add preventDefault handler
		removeButton.addEventListener('click', clickHandler, { once: true });
		
		// Try the plugin's native removal via data attributes
		if (removeButton.dataset.storage) {
			// Try to trigger plugin's removal logic via the data-storage attribute
			this._triggerPluginRemoval(removeButton, item);
		}
		
		// Restore href after attempt
		setTimeout(() => {
			removeButton.href = originalHref;
		}, 100);
		
		// Check if removal worked
		setTimeout(() => {
			if (document.contains(item)) {
				console.log("[PreviewManager] Plugin removal failed, using smooth manual removal");
				this._smoothRemoval(item);
			} else {
				console.log("[PreviewManager] Item successfully removed");
			}
		}, 150);
	}

	/**
	 * Try to trigger plugin's native removal
	 * @private
	 * @param {HTMLElement} removeButton 
	 * @param {HTMLElement} item 
	 */
	_triggerPluginRemoval(removeButton, item) {
		// Look for global plugin functions
		if (window.codedropz || window.Codedropz) {
			console.log("[PreviewManager] Found plugin global object");
			// Try to call plugin's remove function if it exists
		}

		// Try jQuery approach if available
		if (window.jQuery) {
			const $button = window.jQuery(removeButton);
			
			// Try triggering click via jQuery
			$button.trigger('click');
			
			// Check for bound events
			const events = $button.data('events') || jQuery._data(removeButton, 'events');
			if (events && events.click) {
				console.log("[PreviewManager] Found jQuery click events, triggering manually");
				events.click.forEach(eventHandler => {
					try {
						eventHandler.handler.call(removeButton);
					} catch (e) {
						console.log("[PreviewManager] Error calling jQuery handler:", e);
					}
				});
			}
		}

		// Try direct DOM event approach
		removeButton.click();
	}

	/**
	 * Simple, performant removal animation
	 * @private
	 * @param {HTMLElement} item 
	 */
	_smoothRemoval(item) {
		console.log("[PreviewManager] Performing clean removal");
		
		// Use View Transitions API if available
		if ('startViewTransition' in document) {
			item.classList.add('removing');
			document.startViewTransition(() => {
				if (item.parentNode) {
					item.parentNode.removeChild(item);
					console.log("[PreviewManager] Item removed with View Transition");
				}
			});
		} else {
			// Fallback for browsers without View Transitions API
			item.style.transition = 'opacity 0.3s ease-out';
			item.style.opacity = '0';
			setTimeout(() => {
				if (item.parentNode) {
					item.parentNode.removeChild(item);
					console.log("[PreviewManager] Item removed with fallback animation");
				}
			}, 300);
		}
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
	 * Enhance thumbnail display with actual file previews
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

		// Get the actual file from the file input to create real thumbnail
		const fileName = this._getFileName(item);
		if (fileName && this._isImageFile(fileName)) {
			console.log("[PreviewManager] Creating real thumbnail for image:", fileName);
			this._createRealImageThumbnail(item, imageContainer, fileName);
		} else {
			console.log("[PreviewManager] Non-image file, using fallback icon for:", fileName || 'unknown file');
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
	 * Create real image thumbnail from actual file data
	 * @private
	 * @param {HTMLElement} item 
	 * @param {HTMLElement} imageContainer 
	 * @param {string} fileName 
	 */
	_createRealImageThumbnail(item, imageContainer, fileName) {
		// Get the file input element
		const fileInput = document.querySelector('#realize-upload, input[data-name="mfile-747"], .wpcf7-drag-n-drop-file');
		if (!fileInput || !fileInput.files) {
			console.log("[PreviewManager] No file input or files found");
			return;
		}

		// Find the file that matches this preview item
		const matchingFile = Array.from(fileInput.files).find(file => file.name === fileName);
		if (!matchingFile) {
			console.log("[PreviewManager] No matching file found for:", fileName);
			return;
		}

		console.log("[PreviewManager] Found matching file:", matchingFile);

		// Create thumbnail using FileReader
		const reader = new FileReader();
		reader.onload = (e) => {
			console.log("[PreviewManager] File read successfully, creating thumbnail");
			
			// Create img element with the file data
			const img = document.createElement('img');
			img.src = e.target.result;
			img.style.width = '100%';
			img.style.height = '100%';
			img.style.objectFit = 'cover';
			img.style.borderRadius = '5px';
			img.style.position = 'relative';
			img.style.zIndex = '2';
			img.style.display = 'block';
			
			// Add to container
			imageContainer.appendChild(img);
			console.log("[PreviewManager] Real thumbnail created successfully");
		};

		reader.onerror = () => {
			console.log("[PreviewManager] Error reading file for thumbnail");
		};

		// Read the file as data URL
		reader.readAsDataURL(matchingFile);
	}
}