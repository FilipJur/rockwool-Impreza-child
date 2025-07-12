/**
 * ACF Number Field Formatting
 * 
 * Enhances ACF number fields with:
 * - Real-time thousands separator formatting (space)
 * - Proper value parsing for form submission
 * - Visual feedback for points and currency fields
 */

class ACFNumberFormatter {
    constructor() {
        this.init();
    }

    init() {
        if (typeof acf === 'undefined') {
            console.warn('ACF not available for number formatting');
            return;
        }

        this.setupACFHooks();
        this.enhanceExistingFields();
    }

    setupACFHooks() {
        // Hook into ACF's field initialization
        acf.addAction('ready_field/type=number', (field) => {
            this.enhanceNumberField(field);
        });

        acf.addAction('append_field/type=number', (field) => {
            this.enhanceNumberField(field);
        });
    }

    enhanceExistingFields() {
        // Enhance already loaded number fields
        document.querySelectorAll('.acf-field-number input[type="number"]').forEach(input => {
            this.enhanceInput(input);
        });
    }

    enhanceNumberField(field) {
        const input = field.find('input[type="number"]').get(0);
        if (input) {
            this.enhanceInput(input);
        }
    }

    enhanceInput(input) {
        // Skip if already enhanced
        if (input.dataset.formattingEnhanced) {
            return;
        }

        input.dataset.formattingEnhanced = 'true';

        // Check if this is a points or currency field based on field name
        const isPointsField = this.isPointsField(input);
        const isCurrencyField = this.isCurrencyField(input);

        if (!isPointsField && !isCurrencyField) {
            return; // Only format points and currency fields
        }

        // Create display input for formatted value
        const displayInput = this.createDisplayInput(input, isPointsField);
        
        // Set up event handlers
        this.setupEventHandlers(input, displayInput, isPointsField);

        // Format initial value
        this.formatDisplayValue(input, displayInput, isPointsField);
    }

    isPointsField(input) {
        const fieldName = input.name || '';
        return fieldName.includes('points') || 
               fieldName.includes('body') || 
               fieldName.includes('awarded') ||
               input.closest('.acf-field').querySelector('label')?.textContent?.toLowerCase().includes('bod');
    }

    isCurrencyField(input) {
        const fieldName = input.name || '';
        return fieldName.includes('amount') || 
               fieldName.includes('value') || 
               fieldName.includes('castka') ||
               input.closest('.acf-field').querySelector('label')?.textContent?.toLowerCase().includes('částka');
    }

    createDisplayInput(originalInput, isPointsField) {
        const displayInput = document.createElement('input');
        displayInput.type = 'text';
        displayInput.className = originalInput.className + ' acf-formatted-display';
        displayInput.placeholder = isPointsField ? 'např. 25 000' : 'např. 125 000';
        
        // Copy styling
        displayInput.style.cssText = originalInput.style.cssText;
        
        // Hide original input
        originalInput.style.display = 'none';
        
        // Insert display input after original
        originalInput.parentNode.insertBefore(displayInput, originalInput.nextSibling);
        
        return displayInput;
    }

    setupEventHandlers(originalInput, displayInput, isPointsField) {
        // Format on display input change
        displayInput.addEventListener('input', () => {
            const cleanValue = this.parseFormattedNumber(displayInput.value);
            originalInput.value = cleanValue;
            this.formatDisplayValue(originalInput, displayInput, isPointsField);
        });

        displayInput.addEventListener('blur', () => {
            this.formatDisplayValue(originalInput, displayInput, isPointsField);
        });

        // Sync when original input changes (programmatically)
        const observer = new MutationObserver(() => {
            this.formatDisplayValue(originalInput, displayInput, isPointsField);
        });
        
        observer.observe(originalInput, {
            attributes: true,
            attributeFilter: ['value']
        });

        // Also listen for value changes
        originalInput.addEventListener('change', () => {
            this.formatDisplayValue(originalInput, displayInput, isPointsField);
        });
    }

    formatDisplayValue(originalInput, displayInput, isPointsField) {
        const value = parseInt(originalInput.value) || 0;
        
        if (value === 0) {
            displayInput.value = '';
            return;
        }

        const formatted = this.formatNumber(value);
        displayInput.value = formatted;
        
        // Add suffix hint in placeholder when empty
        if (!displayInput.value) {
            displayInput.placeholder = isPointsField ? 'např. 25 000 bodů' : 'např. 125 000 Kč';
        }
    }

    formatNumber(number) {
        if (!number || isNaN(number)) {
            return '';
        }
        
        return new Intl.NumberFormat('cs-CZ', {
            useGrouping: true,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(number).replace(/\s/g, ' '); // Ensure consistent space character
    }

    parseFormattedNumber(formattedString) {
        if (!formattedString) {
            return 0;
        }
        
        // Remove all non-digit characters except comma (decimal separator)
        const cleaned = formattedString.replace(/[^\d,]/g, '');
        
        // Convert to number
        const number = parseInt(cleaned.replace(',', '')) || 0;
        
        return Math.max(0, number); // Ensure non-negative
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new ACFNumberFormatter();
});

// Also initialize if ACF is already loaded
if (typeof acf !== 'undefined') {
    acf.addAction('ready', () => {
        new ACFNumberFormatter();
    });
}