/**
 * WooCommerce Integration Module
 * Handles cart updates and button state refreshing for myCred point system
 */

import { api } from '../utils/api.js';

export class WooCommerceIntegration {
  constructor() {
    this.isInitialized = false;
    this.eventHandlers = {};
    this.refreshInProgress = false;
    
    this.init();
  }

  /**
   * Initialize the WooCommerce integration
   */
  init() {
    if (this.isInitialized) return;

    this.bindWooCommerceEvents();
    this.bindCustomEvents();
    this.isInitialized = true;
    
    console.log('WooCommerce integration initialized');
  }

  /**
   * Bind to WooCommerce's built-in events
   */
  bindWooCommerceEvents() {
    // Handle successful add to cart
    this.eventHandlers.addedToCart = (event) => {
      console.log('Product added to cart, refreshing buttons');
      this.refreshProductButtons();
    };

    // Handle cart updates (including mini cart removal)
    this.eventHandlers.updatedWcDiv = (event) => {
      console.log('WooCommerce fragments updated, refreshing buttons');
      this.refreshProductButtons();
    };

    // Handle cart item removal
    this.eventHandlers.removedFromCart = (event) => {
      console.log('Product removed from cart, refreshing buttons');
      this.refreshProductButtons();
    };

    // Handle cart totals update
    this.eventHandlers.updatedCartTotals = (event) => {
      console.log('Cart totals updated, refreshing buttons');
      this.refreshProductButtons();
    };

    // Bind events to document
    document.addEventListener('added_to_cart', this.eventHandlers.addedToCart);
    document.addEventListener('updated_wc_div', this.eventHandlers.updatedWcDiv);
    document.addEventListener('removed_from_cart', this.eventHandlers.removedFromCart);
    document.addEventListener('updated_cart_totals', this.eventHandlers.updatedCartTotals);

    // Also bind to body for events that bubble differently
    document.body.addEventListener('wc_fragments_refreshed', (event) => {
      console.log('WC fragments refreshed, refreshing buttons');
      this.refreshProductButtons();
    });

    // Handle AJAX cart operations completion
    document.addEventListener('wc_fragments_loaded', (event) => {
      console.log('WC fragments loaded, refreshing buttons');
      this.refreshProductButtons();
    });
  }

  /**
   * Bind custom events for manual triggering
   */
  bindCustomEvents() {
    // Custom event for manual button refresh
    document.addEventListener('mycred_refresh_buttons', (event) => {
      console.log('Manual button refresh requested');
      this.refreshProductButtons();
    });

    // Listen for mini cart updates (common class-based detection)
    this.observeMinicartChanges();
  }

  /**
   * Observe mini cart for changes using MutationObserver
   */
  observeMinicartChanges() {
    const minicartSelectors = [
      '.widget_shopping_cart_content',
      '.woocommerce-mini-cart',
      '.cart-contents',
      '.mini-cart',
      '.wc-block-mini-cart'
    ];

    minicartSelectors.forEach(selector => {
      const minicart = document.querySelector(selector);
      if (minicart) {
        const observer = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => {
            if (mutation.type === 'childList' || mutation.type === 'subtree') {
              console.log('Mini cart content changed, refreshing buttons');
              this.refreshProductButtons();
            }
          });
        });

        observer.observe(minicart, {
          childList: true,
          subtree: true,
          attributes: false
        });

        console.log(`Mini cart observer attached to ${selector}`);
      }
    });
  }

  /**
   * Refresh all product buttons based on current myCred affordability
   */
  async refreshProductButtons() {
    if (this.refreshInProgress) {
      console.log('Button refresh already in progress, skipping');
      return;
    }

    this.refreshInProgress = true;

    try {
      // Add a small delay to ensure cart operations are complete
      await this.delay(100);

      // Get all add to cart buttons
      const buttons = document.querySelectorAll('.add_to_cart_button, .single_add_to_cart_button');
      
      if (buttons.length === 0) {
        console.log('No add to cart buttons found');
        return;
      }

      console.log(`Refreshing ${buttons.length} product buttons`);

      // Get current cart state and user balance from server
      const cartData = await this.getCartState();
      
      // Update each button
      buttons.forEach(button => {
        this.updateButton(button, cartData);
      });

      console.log('Product buttons refreshed successfully');

    } catch (error) {
      console.error('Failed to refresh product buttons:', error);
    } finally {
      this.refreshInProgress = false;
    }
  }

  /**
   * Get current cart state from server via AJAX
   */
  async getCartState() {
    try {
      const response = await api.wpAjax('mycred_get_cart_state', {});
      
      if (response.success) {
        return response.data;
      } else {
        throw new Error(response.data || 'Failed to get cart state');
      }
    } catch (error) {
      console.error('Failed to get cart state:', error);
      return null;
    }
  }

  /**
   * Update individual button state
   */
  updateButton(button, cartData) {
    if (!cartData) return;

    const productId = this.getProductIdFromButton(button);
    if (!productId) return;

    const isAffordable = this.isProductAffordable(productId, cartData);
    
    if (isAffordable) {
      this.makeButtonAffordable(button);
    } else {
      this.makeButtonUnaffordable(button);
    }
  }

  /**
   * Extract product ID from button element
   */
  getProductIdFromButton(button) {
    // Try various methods to get product ID
    let productId = null;

    // Method 1: data-product_id attribute
    productId = button.getAttribute('data-product_id');
    if (productId) return parseInt(productId);

    // Method 2: value attribute (for simple products)
    productId = button.getAttribute('value');
    if (productId) return parseInt(productId);

    // Method 3: href parsing for external products
    const href = button.getAttribute('href');
    if (href && href.includes('add-to-cart=')) {
      const match = href.match(/add-to-cart=(\d+)/);
      if (match) return parseInt(match[1]);
    }

    // Method 4: closest product container
    const productContainer = button.closest('[data-product-id], .product');
    if (productContainer) {
      productId = productContainer.getAttribute('data-product-id') || 
                  productContainer.getAttribute('data-id');
      if (productId) return parseInt(productId);
    }

    return null;
  }

  /**
   * Check if product is affordable based on cart data
   */
  isProductAffordable(productId, cartData) {
    const { userBalance, cartTotal, products } = cartData;
    
    const product = products[productId];
    if (!product) return true; // Default to affordable if no data

    const productCost = product.cost;
    const cartExcludingProduct = cartTotal - (product.inCart ? productCost * product.quantity : 0);
    const potentialTotal = cartExcludingProduct + productCost;

    return potentialTotal <= userBalance;
  }

  /**
   * Make button appear affordable
   */
  makeButtonAffordable(button) {
    button.classList.remove('disabled', 'mycred-insufficient-points');
    button.removeAttribute('disabled');
    
    // Restore original text if it was changed
    const originalText = button.getAttribute('data-original-text');
    if (originalText) {
      button.textContent = originalText;
    }

    // Remove any custom styling
    button.style.removeProperty('background-color');
    button.style.removeProperty('color');
    button.style.removeProperty('cursor');
  }

  /**
   * Make button appear unaffordable
   */
  makeButtonUnaffordable(button) {
    // Store original text if not already stored
    if (!button.getAttribute('data-original-text')) {
      button.setAttribute('data-original-text', button.textContent);
    }

    button.classList.add('disabled', 'mycred-insufficient-points');
    button.setAttribute('disabled', 'disabled');
    button.textContent = 'Not enough points (inc. cart)';

    // Apply styling
    button.style.backgroundColor = '#e0e0e0';
    button.style.color = '#888888';
    button.style.cursor = 'not-allowed';
  }

  /**
   * Utility: Add delay
   */
  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Manually trigger button refresh (public method)
   */
  refresh() {
    this.refreshProductButtons();
  }

  /**
   * Check if module is ready
   */
  isReady() {
    return this.isInitialized;
  }

  /**
   * Destroy the module
   */
  destroy() {
    // Remove event listeners
    Object.keys(this.eventHandlers).forEach(event => {
      document.removeEventListener(event, this.eventHandlers[event]);
    });

    this.eventHandlers = {};
    this.isInitialized = false;
    
    console.log('WooCommerce integration destroyed');
  }
}