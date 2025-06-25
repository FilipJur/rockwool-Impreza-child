/**
 * Logger Utility
 *
 * Provides conditional logging based on environment.
 * Prevents console output in production while maintaining debug capabilities.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

/**
 * Determine if we're in development mode
 * Checks multiple indicators for development environment
 */
const isDevelopment = () => {
  return (
    // Check if NODE_ENV is set to development (webpack)
    (typeof process !== 'undefined' && process.env && process.env.NODE_ENV === 'development') ||
    // Check for localhost or development domains
    window.location.hostname === 'localhost' ||
    window.location.hostname === '127.0.0.1' ||
    window.location.hostname.includes('.local') ||
    window.location.hostname.includes('.dev') ||
    window.location.hostname.includes('.test') ||
    // Check for debug query parameter
    new URLSearchParams(window.location.search).has('debug') ||
    // Check for WordPress debug mode indicator
    window.mistrFachman?.debug === true
  );
};

/**
 * Logger class with conditional output
 */
class Logger {
  constructor(context = 'App') {
    this.context = context;
    this.isDev = isDevelopment();
  }

  /**
   * Info level logging
   * @param {string} message - Log message
   * @param {...any} args - Additional arguments
   */
  info(message, ...args) {
    if (this.isDev) {
      console.log(`[${this.context}] ${message}`, ...args);
    }
  }

  /**
   * Warning level logging
   * @param {string} message - Log message
   * @param {...any} args - Additional arguments
   */
  warn(message, ...args) {
    if (this.isDev) {
      console.warn(`[${this.context}] ${message}`, ...args);
    }
  }

  /**
   * Error level logging (always shown, even in production)
   * @param {string} message - Log message
   * @param {...any} args - Additional arguments
   */
  error(message, ...args) {
    console.error(`[${this.context}] ${message}`, ...args);
  }

  /**
   * Debug level logging (development only)
   * @param {string} message - Log message
   * @param {...any} args - Additional arguments
   */
  debug(message, ...args) {
    if (this.isDev) {
      console.debug(`[${this.context}] DEBUG: ${message}`, ...args);
    }
  }

  /**
   * Success level logging
   * @param {string} message - Log message
   * @param {...any} args - Additional arguments
   */
  success(message, ...args) {
    if (this.isDev) {
      console.log(`[${this.context}] ✅ ${message}`, ...args);
    }
  }

  /**
   * Group logging for better organization
   * @param {string} groupTitle - Title for the log group
   * @param {Function} callback - Function to execute within the group
   */
  group(groupTitle, callback) {
    if (this.isDev) {
      console.group(`[${this.context}] ${groupTitle}`);
      callback();
      console.groupEnd();
    } else {
      // Execute callback without grouping in production
      callback();
    }
  }

  /**
   * Create a child logger with extended context
   * @param {string} subContext - Additional context
   * @returns {Logger} New logger instance
   */
  child(subContext) {
    return new Logger(`${this.context}:${subContext}`);
  }
}

/**
 * Create logger instances for different parts of the application
 */
export const createLogger = (context) => new Logger(context);

// Default logger instances
export const logger = new Logger('MistrFachman');
export const adminLogger = new Logger('Admin');
export const realizaceLogger = new Logger('Realizace');
export const fakturyLogger = new Logger('Faktury');

// Legacy console replacement for gradual migration
export const console = {
  log: (message, ...args) => logger.info(message, ...args),
  warn: (message, ...args) => logger.warn(message, ...args),
  error: (message, ...args) => logger.error(message, ...args),
  debug: (message, ...args) => logger.debug(message, ...args),
  info: (message, ...args) => logger.info(message, ...args)
};

export default logger;
