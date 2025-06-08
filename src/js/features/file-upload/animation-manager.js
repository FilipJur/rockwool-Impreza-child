/**
 * Animation Manager Module
 * Handles staggered animations and View Transitions
 */

export class AnimationManager {
	constructor(config) {
		this.config = config;
		this.itemCounter = 0;
	}

	/**
	 * Apply staggered animation to an item
	 * @param {HTMLElement} item 
	 * @param {HTMLElement} container 
	 */
	applyStaggeredAnimation(item, container) {
		if (!item || !container) return;

		// Calculate delay based on existing items
		const existingItems = container.querySelectorAll('.dnd-upload-status.file-preview-item-enhanced');
		const itemIndex = existingItems.length - 1;
		const delay = itemIndex * this.config.animation.staggerDelay;

		item.style.animationDelay = `${delay}s`;

		// Add View Transition name if supported
		if (document.startViewTransition) {
			const uniqueName = `file-preview-${Date.now()}-${this.itemCounter++}`;
			item.style.viewTransitionName = uniqueName;
			console.log("[AnimationManager] Applied view transition name:", uniqueName);
		}

		console.log("[AnimationManager] Applied staggered animation with delay:", delay);
	}

	/**
	 * Trigger view transition for file list changes
	 * @param {Function} callback 
	 */
	triggerViewTransition(callback) {
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
	animateRemoval(item, onComplete) {
		if (!item) return;

		item.classList.add('removing');
		
		// Use CSS animation duration + buffer
		const duration = 300;
		
		setTimeout(() => {
			if (onComplete) onComplete();
		}, duration);
	}
}