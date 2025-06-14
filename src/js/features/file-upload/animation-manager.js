/**
 * Animation utilities for file upload components
 * Handles staggered animations and View Transitions
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

	// Add View Transition name if supported
	if (document.startViewTransition) {
		const uniqueName = `file-preview-${Date.now()}-${itemCounter++}`;
		item.style.viewTransitionName = uniqueName;
		console.log("[Animation] Applied view transition name:", uniqueName);
	}

	console.log("[Animation] Applied staggered animation with delay:", delay);
}

/**
 * Trigger view transition for file list changes
 * @param {Function} callback 
 */
export function triggerViewTransition(callback) {
	if (document.startViewTransition) {
		document.startViewTransition(callback);
	} else {
		callback();
	}
}

/**
 * Add removal animation to item
 * @param {HTMLElement} item 
 * @param {Function} onComplete 
 */
export function animateRemoval(item, onComplete) {
	if (!item) return;

	item.classList.add('removing');
	
	// Use CSS animation duration + buffer
	const duration = 300;
	
	setTimeout(() => {
		if (onComplete) onComplete();
	}, duration);
}