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
			// Don't trigger if clicking directly on remove button
			if (e.target.closest('a[class*="remove"]')) return;

			e.preventDefault();
			e.stopPropagation();

			console.log("[PreviewManager] Preview clicked, attempting removal");
			console.log("[PreviewManager] Item structure:", item.outerHTML);

			const removeButton = this._findRemoveButton(item);
			if (removeButton) {
				console.log("[PreviewManager] Found remove button:", removeButton);
				this._triggerRemoval(removeButton);
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
		// Try multiple removal methods
		if (removeButton.onclick) {
			// If has onclick handler, call it
			removeButton.onclick.call(removeButton);
		} else if (removeButton.href && removeButton.href !== '#' && !removeButton.href.includes('javascript:')) {
			// If has real href, navigate
			window.location.href = removeButton.href;
		} else {
			// Simulate native click
			const event = new MouseEvent('click', {
				bubbles: true,
				cancelable: true,
				view: window
			});
			removeButton.dispatchEvent(event);
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
			// Image already exists, ensure it's properly styled
			existingImg.style.display = 'block';
			existingImg.style.zIndex = '2';
		} else {
			console.log("[PreviewManager] No thumbnail, using fallback icon");
			// No thumbnail available, fallback icon will show via CSS
		}
	}
}