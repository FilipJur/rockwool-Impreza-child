/**
 * Contact Form 7 Number Field Formatting - TARGETED VERSION
 * 
 * ONLY formats the invoice_value input in .faktura-form
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
        // ONLY target the specific invoice_value input in .faktura-form
        const input = document.querySelector('.faktura-form input[type="number"][name="invoice_value"]');
        
        if (input && !input.dataset.formattingActive) {
            this.enhanceNumberInput(input);
        }
    }

    enhanceNumberInput(input) {
        input.dataset.formattingActive = 'true';

        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        
        // Create display input
        const displayInput = document.createElement('input');
        displayInput.type = 'text';
        displayInput.className = input.className.replace('wpcf7-number', 'wpcf7-text');
        
        // Copy attributes
        if (input.hasAttribute('required')) displayInput.required = true;
        if (input.hasAttribute('aria-required')) displayInput.setAttribute('aria-required', 'true');
        if (input.hasAttribute('min')) displayInput.setAttribute('data-min', input.getAttribute('min'));
        
        // Insert wrapper
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        wrapper.appendChild(displayInput);
        
        // Hide number input
        input.style.position = 'absolute';
        input.style.opacity = '0';
        input.style.pointerEvents = 'none';
        input.style.height = '1px';
        input.style.width = '1px';
        
        // Setup events
        displayInput.addEventListener('input', (e) => {
            const value = e.target.value;
            const digits = value.replace(/\D/g, '');
            
            if (!digits) {
                input.value = '';
                return;
            }
            
            // Update hidden input
            input.value = digits;
            
            // Format display
            const formatted = parseInt(digits).toLocaleString('cs-CZ').replace(/,/g, ' ');
            if (value !== formatted) {
                const cursorPos = e.target.selectionStart;
                e.target.value = formatted;
                
                // Maintain cursor position
                const diff = formatted.length - value.length;
                e.target.setSelectionRange(cursorPos + diff, cursorPos + diff);
            }
        });
        
        // Handle validation
        input.addEventListener('invalid', (e) => {
            e.preventDefault();
            displayInput.setCustomValidity(input.validationMessage);
            displayInput.reportValidity();
        });
        
        displayInput.addEventListener('invalid', (e) => {
            e.preventDefault();
        });
    }
}

// Initialize
new CF7NumberFormatter();