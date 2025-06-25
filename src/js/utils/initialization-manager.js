/**
 * Initialization Manager
 * 
 * Centralized state management for module initialization.
 * Replaces global variable pollution with clean singleton pattern.
 * 
 * @package mistr-fachman
 * @since 1.0.0
 */

// Simple console logging

/**
 * Initialization Manager Singleton
 * Tracks initialization state of various modules
 */
class InitializationManager {
  constructor() {
    if (InitializationManager.instance) {
      return InitializationManager.instance;
    }

    this.initialized = new Map();
    this.instances = new Map();
    
    InitializationManager.instance = this;
    console.log('[InitManager] Initialization manager created');
  }

  /**
   * Mark a module as initialized
   * @param {string} moduleName - Name of the module
   * @param {*} instance - Module instance (optional)
   */
  setInitialized(moduleName, instance = null) {
    if (this.initialized.has(moduleName)) {
      console.warn(`[InitManager] Module '${moduleName}' was already initialized`);
    }
    
    this.initialized.set(moduleName, true);
    
    if (instance) {
      this.instances.set(moduleName, instance);
    }
    
    console.log(`[InitManager] Module '${moduleName}' marked as initialized`);
  }

  /**
   * Check if a module is initialized
   * @param {string} moduleName - Name of the module
   * @returns {boolean} Whether the module is initialized
   */
  isInitialized(moduleName) {
    return this.initialized.get(moduleName) === true;
  }

  /**
   * Get module instance
   * @param {string} moduleName - Name of the module
   * @returns {*} Module instance or null
   */
  getInstance(moduleName) {
    return this.instances.get(moduleName) || null;
  }

  /**
   * Mark a module as not initialized and remove instance
   * @param {string} moduleName - Name of the module
   */
  setUninitialized(moduleName) {
    this.initialized.delete(moduleName);
    this.instances.delete(moduleName);
    console.log(`[InitManager] Module '${moduleName}' marked as uninitialized`);
  }

  /**
   * Get all initialized modules
   * @returns {string[]} Array of initialized module names
   */
  getInitializedModules() {
    return Array.from(this.initialized.keys()).filter(key => 
      this.initialized.get(key) === true
    );
  }

  /**
   * Reset all initialization state
   * Useful for testing or cleanup
   */
  reset() {
    const moduleCount = this.initialized.size;
    this.initialized.clear();
    this.instances.clear();
    console.log(`[InitManager] Reset initialization state for ${moduleCount} modules`);
  }

  /**
   * Get initialization statistics
   * @returns {Object} Statistics object
   */
  getStats() {
    const initializedCount = this.getInitializedModules().length;
    const totalCount = this.initialized.size;
    
    return {
      initialized: initializedCount,
      total: totalCount,
      modules: this.getInitializedModules(),
      hasInstances: this.instances.size
    };
  }

  /**
   * Prevent duplicate initialization with a guard
   * @param {string} moduleName - Name of the module
   * @param {Function} initCallback - Initialization function
   * @returns {*} Result of initialization or existing instance
   */
  initializeOnce(moduleName, initCallback) {
    if (this.isInitialized(moduleName)) {
      console.warn(`[InitManager] Attempted to re-initialize '${moduleName}' - returning existing instance`);
      return this.getInstance(moduleName);
    }

    console.log(`[InitManager] Initializing '${moduleName}' for the first time`);
    const instance = initCallback();
    this.setInitialized(moduleName, instance);
    
    return instance;
  }

  /**
   * Clean up specific module
   * @param {string} moduleName - Name of the module
   * @param {Function} cleanupCallback - Optional cleanup function
   */
  cleanup(moduleName, cleanupCallback = null) {
    const instance = this.getInstance(moduleName);
    
    if (cleanupCallback && typeof cleanupCallback === 'function') {
      cleanupCallback(instance);
    } else if (instance && typeof instance.cleanup === 'function') {
      instance.cleanup();
    }
    
    this.setUninitialized(moduleName);
    console.log(`[InitManager] Cleaned up module '${moduleName}'`);
  }
}

// Create and export singleton instance
const initManager = new InitializationManager();

export default initManager;

// Export specific methods for convenience
export const {
  setInitialized,
  isInitialized,
  getInstance,
  setUninitialized,
  getInitializedModules,
  reset,
  getStats,
  initializeOnce,
  cleanup
} = initManager;