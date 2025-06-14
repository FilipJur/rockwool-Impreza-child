/**
 * File Removal Handler Module
 * Handles file removal interactions and strategies
 */

/**
 * Attempt file removal using multiple strategies
 * @param {HTMLElement} removeButton 
 * @param {HTMLElement} item 
 */
export function attemptRemoval(removeButton, item) {
	const originalHref = removeButton.href;
	removeButton.href = 'javascript:void(0)';

	const clickHandler = (e) => {
		e.preventDefault();
		e.stopPropagation();
		console.log("[RemovalHandler] Click intercepted, preventing scroll");
	};

	removeButton.addEventListener('click', clickHandler, { once: true });

	if (removeButton.dataset.storage) {
		triggerPluginRemoval(removeButton, item);
	}

	setTimeout(() => {
		removeButton.href = originalHref;
	}, 100);

	setTimeout(() => {
		if (document.contains(item)) {
			console.log("[RemovalHandler] Plugin removal failed, using smooth manual removal");
			smoothRemoval(item);
		} else {
			console.log("[RemovalHandler] Item successfully removed");
		}
	}, 150);
}

/**
 * Try to trigger plugin's native removal
 * @param {HTMLElement} removeButton 
 * @param {HTMLElement} item 
 */
function triggerPluginRemoval(removeButton, item) {
	if (window.codedropz || window.Codedropz) {
		console.log("[RemovalHandler] Found plugin global object");
	}

	if (window.jQuery) {
		const $button = window.jQuery(removeButton);
		$button.trigger('click');

		const events = $button.data('events') || jQuery._data(removeButton, 'events');
		if (events && events.click) {
			console.log("[RemovalHandler] Found jQuery click events, triggering manually");
			events.click.forEach(eventHandler => {
				try {
					eventHandler.handler.call(removeButton);
				} catch (e) {
					console.log("[RemovalHandler] Error calling jQuery handler:", e);
				}
			});
		}
	}

	removeButton.click();
}

/**
 * Simple, performant removal animation
 * @param {HTMLElement} item 
 */
function smoothRemoval(item) {
	console.log("[RemovalHandler] Performing clean removal");

	if ('startViewTransition' in document) {
		item.classList.add('removing');
		document.startViewTransition(() => {
			if (item.parentNode) {
				item.parentNode.removeChild(item);
				console.log("[RemovalHandler] Item removed with View Transition");
			}
		});
	} else {
		item.style.transition = 'opacity 0.3s ease-out';
		item.style.opacity = '0';
		setTimeout(() => {
			if (item.parentNode) {
				item.parentNode.removeChild(item);
				console.log("[RemovalHandler] Item removed with fallback animation");
			}
		}, 300);
	}
}

/**
 * Find the remove button using various selectors
 * @param {HTMLElement} item 
 * @returns {HTMLElement|null}
 */
export function findRemoveButton(item) {
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
			element.removeAttribute('href');
			return element.tagName === 'A' ? element : element.closest('a');
		}
	}

	return null;
}