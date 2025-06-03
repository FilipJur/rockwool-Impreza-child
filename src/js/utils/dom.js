/**
 * DOM utility functions
 * Replaces jQuery for common DOM operations
 */

export const dom = {
  /**
   * Find element(s) by selector
   * @param {string} selector - CSS selector
   * @param {Element} context - Optional context element
   * @returns {Element|NodeList|null}
   */
  find(selector, context = document) {
    const elements = context.querySelectorAll(selector);
    return elements.length === 1 ? elements[0] : elements.length > 1 ? elements : null;
  },

  /**
   * Find single element by selector
   * @param {string} selector - CSS selector
   * @param {Element} context - Optional context element
   * @returns {Element|null}
   */
  findOne(selector, context = document) {
    return context.querySelector(selector);
  },

  /**
   * Check if element exists
   * @param {string} selector - CSS selector
   * @param {Element} context - Optional context element
   * @returns {boolean}
   */
  exists(selector, context = document) {
    return context.querySelector(selector) !== null;
  },

  /**
   * Add event listener with optional delegation
   * @param {Element|string} target - Element or selector
   * @param {string} event - Event type
   * @param {Function} handler - Event handler
   * @param {string} delegate - Optional selector for delegation
   */
  on(target, event, handler, delegate = null) {
    const element = typeof target === 'string' ? this.findOne(target) : target;
    
    if (!element) return;

    if (delegate) {
      element.addEventListener(event, (e) => {
        if (e.target.matches(delegate) || e.target.closest(delegate)) {
          handler.call(e.target.closest(delegate) || e.target, e);
        }
      });
    } else {
      element.addEventListener(event, handler);
    }
  },

  /**
   * Set element text content
   * @param {Element|string} target - Element or selector
   * @param {string} text - Text content
   */
  text(target, text) {
    const element = typeof target === 'string' ? this.findOne(target) : target;
    if (element) element.textContent = text;
  },

  /**
   * Set element HTML content
   * @param {Element|string} target - Element or selector
   * @param {string} html - HTML content
   */
  html(target, html) {
    const element = typeof target === 'string' ? this.findOne(target) : target;
    if (element) element.innerHTML = html;
  },

  /**
   * Set element styles
   * @param {Element|string} target - Element or selector
   * @param {Object|string} styles - Style object or property name
   * @param {string} value - Style value (if styles is string)
   */
  css(target, styles, value = null) {
    const element = typeof target === 'string' ? this.findOne(target) : target;
    if (!element) return;

    if (typeof styles === 'object') {
      Object.assign(element.style, styles);
    } else if (value !== null) {
      element.style[styles] = value;
    }
  },

  /**
   * Get/set element value
   * @param {Element|string} target - Element or selector
   * @param {string} value - Value to set (optional)
   * @returns {string} Current value if getting
   */
  val(target, value = null) {
    const element = typeof target === 'string' ? this.findOne(target) : target;
    if (!element) return '';

    if (value !== null) {
      element.value = value;
    } else {
      return element.value || '';
    }
  },

  /**
   * Add/remove/toggle CSS classes
   * @param {Element|string} target - Element or selector
   * @param {string} className - Class name
   * @param {string} action - 'add', 'remove', or 'toggle'
   */
  class(target, className, action = 'add') {
    const element = typeof target === 'string' ? this.findOne(target) : target;
    if (!element) return;

    switch (action) {
      case 'add':
        element.classList.add(className);
        break;
      case 'remove':
        element.classList.remove(className);
        break;
      case 'toggle':
        element.classList.toggle(className);
        break;
    }
  },

  /**
   * Wait for DOM ready state
   * @param {Function} callback - Function to execute when ready
   */
  ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  }
};