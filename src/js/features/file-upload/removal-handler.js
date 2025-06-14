/**
 * File Removal Handler Module
 * Handles file removal interactions and strategies
 */

/**
 * Attempt file removal using plugin's native removal
 * @param {HTMLElement} removeButton 
 * @param {HTMLElement} item 
 */
export function attemptRemoval(removeButton, item) {
	console.log("[RemovalHandler] Starting removal process");
	
	// Disable pointer events during removal
	item.classList.add('removing');
	
	const originalHref = removeButton.href;
	removeButton.href = 'javascript:void(0)';

	const clickHandler = (e) => {
		e.preventDefault();
		e.stopPropagation();
		console.log("[RemovalHandler] Click intercepted, preventing scroll");
	};

	removeButton.addEventListener('click', clickHandler, { once: true });

	// Try plugin removal
	if (removeButton.dataset.storage) {
		triggerPluginRemoval(removeButton, item);
	}

	setTimeout(() => {
		removeButton.href = originalHref;
	}, 100);

	// Fallback cleanup if plugin doesn't handle removal
	setTimeout(() => {
		if (document.contains(item)) {
			console.log("[RemovalHandler] Plugin removal failed, removing manually");
			if (item.parentNode) {
				item.parentNode.removeChild(item);
			}
		} else {
			console.log("[RemovalHandler] Item successfully removed by plugin");
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