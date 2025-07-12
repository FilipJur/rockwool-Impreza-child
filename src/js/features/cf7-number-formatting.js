/**
 * Contact Form 7 Number Field Formatting - MINIMAL VERSION
 * 
 * ONLY adds thousands separator formatting to number fields.
 * NO UI changes, hints, labels, or other modifications.
 */

class CF7NumberFormatter {
    constructor() {
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.enhanceFields();
        });

        // Re-enhance after CF7 events
        document.addEventListener('wpcf7mailsent', () => {
            setTimeout(() => this.enhanceFields(), 100);
        });
    }

    enhanceFields() {
        // Only target specific field types that should be formatted
        const selectors = [
            'input[name*="amount"]',
            'input[name*="castka"]', 
            'input[name*="value"]',
            'input[name*="invoice"]',
            'input[name*="faktura"]'
        ];

        const cf7Forms = document.querySelectorAll('.wpcf7-form');
        
        cf7Forms.forEach(form => {
            selectors.forEach(selector => {
                const inputs = form.querySelectorAll(selector);
                inputs.forEach(input => this.enhanceInput(input));
            });
        });
    }

    enhanceInput(input) {
        // Skip if already enhanced
        if (input.dataset.cf7FormattingEnhanced) {
            return;
        }

        input.dataset.cf7FormattingEnhanced = 'true';

        // ONLY add formatting on blur - no real-time formatting to avoid interference
        input.addEventListener('blur', () => {
            this.formatInputValue(input);
        });
    }

    formatInputValue(input) {
        const value = input.value.trim();
        if (!value) return;

        // Parse numeric value
        const numericValue = this.parseNumber(value);
        if (numericValue === 0) return;

        // Format with space separators
        const formatted = this.formatNumber(numericValue);
        input.value = formatted;
    }

    parseNumber(value) {
        // Remove all non-digits
        const cleaned = value.replace(/[^\d]/g, '');
        return parseInt(cleaned) || 0;
    }

    formatNumber(number) {
        if (!number || isNaN(number)) {
            return '';
        }
        
        // Use Czech formatting with space separator
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
}

// Initialize on load
new CF7NumberFormatter();