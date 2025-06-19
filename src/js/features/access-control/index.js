/**
 * Frontend Access Control Module
 * 
 * Provides immediate client-side access control for registration pages
 * based on user status. This works as a first line of defense alongside
 * server-side access control.
 */

/**
 * Setup access control functionality
 * @param {Object} options - Configuration options
 * @returns {Object} Handler object with methods
 */
export function setupAccessControl(options = {}) {
  // Configuration
  const config = {
    registrationPages: ['prihlaseni', 'registrace'],
    enableRedirects: true,
    enableLogging: true,
    ...options
  };

  // State variables
  let isInitialized = false;
  let userStatus = null;

  /**
   * Initialize access control
   */
  function init() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', setup);
    } else {
      setup();
    }
  }

  /**
   * Setup access control
   */
  function setup() {
    getUserStatus();
    checkCurrentPageAccess();
    isInitialized = true;

    if (config.enableLogging) {
      console.log('Access control initialized');
    }
  }

  /**
   * Get user status from server-side data
   */
  function getUserStatus() {
    // Check if user status is available from server
    if (window.mistrFachman && window.mistrFachman.userStatus) {
      userStatus = window.mistrFachman.userStatus;
      return;
    }

    // Fallback: try to determine from DOM or make AJAX call if needed
    if (document.body.classList.contains('logged-in')) {
      // User is logged in but we don't know their exact status
      // This should rarely happen as server should provide the status
      userStatus = 'unknown_logged_in';
    } else {
      userStatus = 'logged_out';
    }
  }

  /**
   * Check if current page access is allowed for current user
   */
  function checkCurrentPageAccess() {
    const currentPage = getCurrentPageSlug();
    
    // Only check access for registration pages
    if (!isRegistrationPage(currentPage)) {
      return;
    }

    const redirectUrl = getRedirectUrl(userStatus, currentPage);
    
    if (redirectUrl && config.enableRedirects) {
      if (config.enableLogging) {
        console.log('Access control: Redirecting user', {
          userStatus: userStatus,
          currentPage: currentPage,
          redirectTo: redirectUrl
        });
      }

      // Immediate redirect
      window.location.href = redirectUrl;
    }
  }

  /**
   * Get current page slug from URL
   */
  function getCurrentPageSlug() {
    const path = window.location.pathname;
    
    // Extract slug from path
    const segments = path.split('/').filter(segment => segment.length > 0);
    
    // For homepage
    if (segments.length === 0) {
      return 'homepage';
    }
    
    // Return the last meaningful segment
    return segments[segments.length - 1];
  }

  /**
   * Check if page is a registration page
   */
  function isRegistrationPage(pageSlug) {
    return config.registrationPages.includes(pageSlug);
  }

  /**
   * Get redirect URL based on user status and current page
   */
  function getRedirectUrl(userStatus, currentPage) {
    switch (userStatus) {
      case 'full_member':
        // Full members should be redirected to My Account
        return getMyAccountUrl();

      case 'awaiting_review':
        // Users awaiting review should be redirected to My Account
        return getMyAccountUrl();

      case 'needs_form':
        // Users who need form should be redirected from login to registration
        if (currentPage === 'prihlaseni') {
          return getRegistrationFormUrl();
        }
        // Allow access to registrace page
        return null;

      case 'other':
      case 'unknown_logged_in':
        // Users with unknown status - redirect to homepage for safety
        return '/';

      case 'logged_out':
        // Logged out users should only have access to prihlaseni
        if (currentPage === 'registrace') {
          return '/prihlaseni';
        }
        return null;

      default:
        // Fallback for any unexpected status
        return '/';
    }
  }

  /**
   * Get My Account URL
   */
  function getMyAccountUrl() {
    // Try to get from server data
    if (window.mistrFachman && window.mistrFachman.myAccountUrl) {
      return window.mistrFachman.myAccountUrl;
    }

    // Common fallbacks
    const commonUrls = ['/muj-ucet', '/my-account', '/account'];
    
    for (const url of commonUrls) {
      // We can't easily check if these pages exist from client-side
      // So we'll use the first fallback
      return url;
    }

    // Final fallback to homepage
    return '/';
  }

  /**
   * Get registration form URL
   */
  function getRegistrationFormUrl() {
    return '/registrace';
  }

  /**
   * Public method to check access for a specific page
   */
  function isPageAccessAllowed(pageSlug, userStatusParam = null) {
    const status = userStatusParam || userStatus;
    return getRedirectUrl(status, pageSlug) === null;
  }

  /**
   * Cleanup function (minimal for this module)
   */
  function cleanup() {
    isInitialized = false;
    userStatus = null;
    if (config.enableLogging) {
      console.log('Access control destroyed');
    }
  }

  // Initialize the handler
  init();

  // Return handler object with public methods
  return {
    cleanup,
    isReady: () => isInitialized,
    getCurrentUserStatus: () => userStatus,
    getCurrentPageSlug,
    isRegistrationPage,
    getRedirectUrl,
    getMyAccountUrl,
    getRegistrationFormUrl,
    isPageAccessAllowed,
    checkCurrentPageAccess,
    getUserStatus,
    getConfig: () => config
  };
}

export default setupAccessControl;