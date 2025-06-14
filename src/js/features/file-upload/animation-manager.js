/**
 * Animation utilities for file upload components
 * Handles staggered animations
 */

let itemCounter = 0;

/**
 * Apply staggered animation to an item
 * @param {HTMLElement} item 
 * @param {HTMLElement} container 
 * @param {Object} config - Animation configuration
 */
export function applyStaggeredAnimation(item, container, config) {
	if (!item || !container || !config?.animation?.staggerDelay) return;

	// Calculate delay based on existing items
	const existingItems = container.querySelectorAll('.dnd-upload-status.file-preview-item-enhanced');
	const itemIndex = existingItems.length - 1;
	const delay = itemIndex * config.animation.staggerDelay;

	item.style.animationDelay = `${delay}s`;

	console.log("[Animation] Applied staggered animation with delay:", delay);
}


