/**
 * Realizace CF7 Integration Module
 * 
 * Provides Alpine.js reactive components for CF7 construction type and material selects.
 * Handles dynamic filtering of materials based on selected construction types.
 * 
 * @package mistr-fachman
 * @since 1.0.0
 */

import Alpine from 'alpinejs';

/**
 * Construction Types Component
 * Multi-select for construction types with change event handling
 * 
 * @returns {void}
 */
export function setupConstructionTypesComponent() {
    Alpine.data('constructionTypes', () => ({
        selectedTypes: [],
        
        init() {
            // Watch for changes and dispatch event for materials component
            this.$watch('selectedTypes', (value) => {
                this.$dispatch('construction-types-changed', { 
                    selectedTypes: value 
                });
            });
        },
        
        handleChange(event) {
            const select = event.target;
            this.selectedTypes = Array.from(select.selectedOptions).map(option => option.value);
        }
    }));
}

/**
 * Materials Component  
 * Checkbox list that filters based on construction type selection
 * 
 * @returns {void}
 */
export function setupMaterialsComponent() {
    Alpine.data('materials', () => ({
        availableMaterials: [],
        selectedMaterials: [],
        loading: false,
        
        init() {
            // Listen for construction type changes
            this.$root.addEventListener('construction-types-changed', (event) => {
                this.updateAvailableMaterials(event.detail.selectedTypes);
            });
        },
        
        /**
         * Update available materials based on construction type selection
         * 
         * @param {Array<string|number>} constructionTypeIds - Array of construction type IDs
         * @returns {Promise<void>}
         */
        async updateAvailableMaterials(constructionTypeIds) {
            if (!constructionTypeIds || constructionTypeIds.length === 0) {
                this.availableMaterials = [];
                this.selectedMaterials = [];
                return;
            }
            
            this.loading = true;
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_materials');
                formData.append('construction_types', JSON.stringify(constructionTypeIds));
                formData.append('nonce', window.mistrFachmanAjax?.nonce || '');
                
                const response = await fetch(window.mistrFachmanAjax?.ajaxurl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.availableMaterials = data.data.materials || [];
                    // Clear selected materials that are no longer available
                    this.selectedMaterials = this.selectedMaterials.filter(selectedId => 
                        this.availableMaterials.some(material => material.term_id == selectedId)
                    );
                } else {
                    console.error('Failed to fetch materials:', data.data);
                    this.availableMaterials = [];
                }
            } catch (error) {
                console.error('Error fetching materials:', error);
                this.availableMaterials = [];
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Toggle selection of a material
         * 
         * @param {string|number} materialId - The material ID to toggle
         * @returns {void}
         */
        toggleMaterial(materialId) {
            const index = this.selectedMaterials.indexOf(materialId);
            if (index > -1) {
                this.selectedMaterials.splice(index, 1);
            } else {
                this.selectedMaterials.push(materialId);
            }
        },
        
        /**
         * Check if a material is currently selected
         * 
         * @param {string|number} materialId - The material ID to check
         * @returns {boolean} True if material is selected
         */
        isMaterialSelected(materialId) {
            return this.selectedMaterials.includes(materialId);
        }
    }));
}

/**
 * Initialize all CF7 integration components
 * Call this function to set up both construction types and materials components
 * 
 * @returns {void}
 */
export function setupRealizaceCF7Integration() {
    setupConstructionTypesComponent();
    setupMaterialsComponent();
}