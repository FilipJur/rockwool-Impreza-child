/**
 * Preview Manager Module
 * Handles file preview interactions and thumbnails
 */

import { attemptRemoval, findRemoveButton } from './removal-handler.js';
import { enhanceThumbnail } from './thumbnail-handler.js';

/**
 * Setup preview observer for a container
 * @param {HTMLElement} container - Container to observe
 * @param {Function} onItemAdded - Callback when item is added
 * @returns {Object} Observer management object
 */
export function setupPreviewObserver(container, onItemAdded) {
	if (!container) {
		console.warn('[PreviewObserver] No container provided');
		return null;
	}

	let observer = null;

	/**
	 * Check if node is a preview item
	 * @param {Node} node
	 * @returns {boolean}
	 */
	function isPreviewItem(node) {
		return node.nodeType === Node.ELEMENT_NODE &&
			   node.classList &&
			   node.classList.contains('dnd-upload-status');
	}

	/**
	 * Enhance a preview item
	 * @param {HTMLElement} item
	 */
	function enhancePreviewItem(item) {
		item.classList.add('file-preview-item-enhanced', 'slide-in');
		makeClickable(item);
		enhanceThumbnail(item);
	}

	/**
	 * Make preview item clickable for removal
	 * @param {HTMLElement} item
	 */
	function makeClickable(item) {
		const clickHandler = (e) => {
			// Don't trigger if clicking directly on remove button or its children
			const removeButton = e.target.closest('a[class*="remove"]');
			if (removeButton) {
				// Let the original button handle its own click
				return;
			}

			// Prevent any default behavior and stop propagation
			e.preventDefault();
			e.stopPropagation();

			console.log("[PreviewObserver] Preview clicked, attempting removal");

			const targetRemoveButton = findRemoveButton(item);
			if (targetRemoveButton) {
				console.log("[PreviewObserver] Found remove button:", targetRemoveButton);
				attemptRemoval(targetRemoveButton, item);
			} else {
				console.warn("[PreviewObserver] No remove button found");
			}
		};

		item.addEventListener('click', clickHandler);
		item.style.cursor = 'pointer';
		item.title = 'Click to remove file';
	}

	/**
	 * Start observing preview items
	 */
	function startObserving() {
		observer = new MutationObserver((mutationsList) => {
			for (const mutation of mutationsList) {
				if (mutation.type === 'childList') {
					mutation.addedNodes.forEach(node => {
						if (isPreviewItem(node)) {
							console.log('[PreviewObserver] Preview item added:', node);
							console.log('[PreviewObserver] Mobile browser:', /iPhone|iPad|iPod|Android/i.test(navigator.userAgent));
							console.log('[PreviewObserver] Node classes:', node.className);
							
							// Enhance preview item
							enhancePreviewItem(node);
							if (onItemAdded) onItemAdded(node);
						}
					});
				}
			}
		});

		observer.observe(container, {
			childList: true,
			subtree: false
		});

		console.log("[PreviewObserver] Started observing preview items");
	}

	/**
	 * Stop observing
	 */
	function stopObserving() {
		if (observer) {
			observer.disconnect();
			observer = null;
		}
	}

	// Auto-start observing
	startObserving();

	// Return control object
	return {
		startObserving,
		stopObserving,
		isObserving: () => observer !== null
	};
}
