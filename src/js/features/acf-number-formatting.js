/**
 * ACF Number Field Formatting - TARGETED VERSION
 * 
 * ONLY formats the invoice_value ACF field in admin
 */

class ACFNumberFormatter {
    constructor() {
        this.init();
    }

    init() {
        if (typeof acf === 'undefined') {
            return;
        }

        // Wait for ACF to be ready
        acf.addAction('ready', () => {
            this.enhanceInvoiceField();
        });

        // Also check on field append (for dynamic fields)
        acf.addAction('append', () => {
            this.enhanceInvoiceField();
        });
    }

    enhanceInvoiceField() {
        // Target ONLY the invoice_value field by ID pattern
        const inputs = document.querySelectorAll('input[type="number"][id*="field_invoice_value"]');
        
        inputs.forEach(input => {
            if (!input.dataset.formattingActive) {
                this.enhanceNumberInput(input);
            }
        });
    }

    enhanceNumberInput(input) {
        input.dataset.formattingActive = 'true';

        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        
        // Create display input
        const displayInput = document.createElement('input');
        displayInput.type = 'text';
        displayInput.className = input.className;
        
        // Copy attributes
        const attrs = ['required', 'min', 'max', 'step'];
        attrs.forEach(attr => {
            if (input.hasAttribute(attr)) {
                displayInput.setAttribute(attr, input.getAttribute(attr));
            }
        });
        
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
        
        // Initialize with current value
        if (input.value) {
            displayInput.value = parseInt(input.value).toLocaleString('cs-CZ').replace(/,/g, ' ');
        }
        
        // Setup events
        displayInput.addEventListener('input', (e) => {
            const value = e.target.value;
            const digits = value.replace(/\D/g, '');
            
            if (!digits) {
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
            
            // Update hidden input
            input.value = digits;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            
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
        
        // Listen for programmatic changes to the hidden input
        const observer = new MutationObserver(() => {
            if (input.value && !displayInput.matches(':focus')) {
                displayInput.value = parseInt(input.value).toLocaleString('cs-CZ').replace(/,/g, ' ');
            }
        });
        
        observer.observe(input, {
            attributes: true,
            attributeFilter: ['value']
        });
        
        // Also listen for value property changes
        input.addEventListener('change', () => {
            if (input.value && !displayInput.matches(':focus')) {
                displayInput.value = parseInt(input.value).toLocaleString('cs-CZ').replace(/,/g, ' ');
            }
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new ACFNumberFormatter();
});