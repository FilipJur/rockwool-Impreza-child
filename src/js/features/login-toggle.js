/**
 * Login/Registration Toggle Functionality
 * 
 * Handles cookie manipulation and page refresh for login/registration state switching
 * Integrates with existing cookie-based state management in functions.php
 */

/**
 * Set a cookie with specified name, value, and expiration
 * @param {string} name - Cookie name
 * @param {string} value - Cookie value
 * @param {number} days - Days until expiration
 */
function setCookie(name, value, days) {
    let expires = "";
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    // Set cookie path to root to ensure it's accessible across the site
    document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

/**
 * Get a cookie value by name
 * @param {string} name - Cookie name
 * @returns {string|null} - Cookie value or null if not found
 */
function getCookie(name) {
    const nameEQ = name + "=";
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

/**
 * Initialize login toggle functionality
 */
function initLoginToggle() {
    const toggleLink = document.querySelector('.lwp_change_pn');
    
    if (!toggleLink) {
        return;
    }
    
    toggleLink.addEventListener('click', function(e) {
        e.preventDefault();
        
        const currentState = getCookie('has_account');
        const isCurrentlyLogin = currentState === 'true';
        
        // Toggle the state
        if (isCurrentlyLogin) {
            // Switch to registration view - remove cookie (or set to false)
            setCookie('has_account', '', -1); // Delete cookie
        } else {
            // Switch to login view
            setCookie('has_account', 'true', 365);
        }
        
        // Reload the page to show the new state
        window.location.reload();
    });
}

/**
 * Setup function following the project's established patterns
 */
export function setupLoginToggle() {
    // Only initialize on login page
    if (window.location.pathname.includes('/prihlaseni')) {
        // Check if DOM is already ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initLoginToggle);
        } else {
            // DOM is already ready, initialize immediately
            initLoginToggle();
        }
    }
}