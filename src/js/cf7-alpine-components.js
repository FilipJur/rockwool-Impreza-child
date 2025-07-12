/**
 * Alpine.js Components for CF7 Realizace Forms
 * 
 * Provides reactive components for construction type and material selection
 * with dynamic filtering and AJAX integration.
 * 
 * @package mistr-fachman
 * @since 1.0.0
 */

// Import CF7 number formatting functionality
import './features/cf7-number-formatting.js';

document.addEventListener('alpine:init', () => {
    console.log('[RealizaceAlpine] Alpine.js initializing CF7 components');
    
    // Get error messages from localized data
    const errorMessages = window.realizaceErrorMessages || {
        loading_failed: 'Nepodařilo se načíst materiály. Zkuste to prosím znovu.',
        network_error: 'Chyba při komunikaci se serverem. Zkontrolujte připojení k internetu.'
    };
    
    Alpine.data('constructionSelector', () => ({
        selectedTypes: [],
        
        init() {
            // Listen for form reset events
            window.addEventListener('reset-realizace-form', () => {
                console.log('[RealizaceAlpine] Construction selector received reset event');
                this.selectedTypes = [];
            });
        },
        
        updateMaterials() {
            console.log('[RealizaceAlpine] updateMaterials called with selectedTypes:', this.selectedTypes);
            
            // Filter out empty values and placeholder text
            let validTypes = this.selectedTypes;
            if (Array.isArray(validTypes)) {
                validTypes = validTypes.filter(type => type && type !== '' && type !== 'Vyberte typ konstrukce...');
            }
            console.log('[RealizaceAlpine] Filtered validTypes:', validTypes);
            
            // Dispatch event for materials selector to listen
            window.dispatchEvent(new CustomEvent('construction-types-changed', {
                detail: { selectedTypes: validTypes }
            }));
            console.log('[RealizaceAlpine] Dispatched construction-types-changed event');
        }
    }));
    
    Alpine.data('materialsSelector', () => ({
        selectedMaterials: [],
        availableMaterials: [],
        loading: false,
        errorMessage: '',
        
        init() {
            console.log('[RealizaceAlpine] Materials selector initialized');
            
            // Listen for construction type changes
            window.addEventListener('construction-types-changed', (event) => {
                console.log('[RealizaceAlpine] Received construction-types-changed event:', event.detail);
                this.loadMaterials(event.detail.selectedTypes);
            });
            
            // Listen for form reset events
            window.addEventListener('reset-realizace-form', () => {
                console.log('[RealizaceAlpine] Materials selector received reset event');
                this.selectedMaterials = [];
                this.availableMaterials = [];
                this.loading = false;
                this.errorMessage = '';
            });
        },
        
        async loadMaterials(constructionTypes) {
            console.log('[RealizaceAlpine] loadMaterials called with:', constructionTypes);
            if (!constructionTypes || constructionTypes.length === 0) {
                console.log('[RealizaceAlpine] No construction types selected, clearing materials');
                this.availableMaterials = [];
                this.selectedMaterials = [];
                return;
            }
            
            console.log('[RealizaceAlpine] Starting AJAX request for materials');
            this.loading = true;
            this.errorMessage = ''; // Clear any previous errors
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_allowed_materials');
                formData.append('nonce', realizaceAjax.nonce);
                
                // Handle both single and multiple selection
                if (Array.isArray(constructionTypes)) {
                    constructionTypes.forEach(type => {
                        formData.append('construction_types[]', type);
                    });
                } else {
                    formData.append('construction_types[]', constructionTypes);
                }
                
                console.log('[RealizaceAlpine] AJAX URL:', realizaceAjax.url);
                console.log('[RealizaceAlpine] Form data:', Object.fromEntries(formData));
                
                const response = await fetch(realizaceAjax.url, {
                    method: 'POST',
                    body: formData
                });
                
                console.log('[RealizaceAlpine] AJAX response status:', response.status);
                const result = await response.json();
                console.log('[RealizaceAlpine] AJAX response data:', result);
                
                if (result.success) {
                    this.availableMaterials = result.data || [];
                    console.log('[RealizaceAlpine] Set availableMaterials to:', this.availableMaterials);
                    // Clear selected materials that are no longer available
                    this.selectedMaterials = this.selectedMaterials.filter(selected =>
                        this.availableMaterials.some(available => available.id == selected)
                    );
                    // Clear any previous error state
                    this.errorMessage = '';
                } else {
                    console.error('[RealizaceAlpine] Failed to load materials:', result.data);
                    this.availableMaterials = [];
                    this.errorMessage = errorMessages.loading_failed;
                }
            } catch (error) {
                console.error('[RealizaceAlpine] Error loading materials:', error);
                this.availableMaterials = [];
                this.errorMessage = errorMessages.network_error;
            } finally {
                this.loading = false;
                console.log('[RealizaceAlpine] AJAX request completed');
            }
        }
    }));
});

// Handle Contact Form 7 form submission success
document.addEventListener('wpcf7mailsent', () => {
    console.log('[RealizaceAlpine] CF7 form submitted successfully, dispatching reset event');
    
    // Dispatch custom event for components to handle their own reset
    window.dispatchEvent(new CustomEvent('reset-realizace-form'));
    
    console.log('[RealizaceAlpine] Reset event dispatched');
});