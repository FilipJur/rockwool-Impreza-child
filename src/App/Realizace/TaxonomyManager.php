<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Taxonomy Manager for Realizace Domain
 *
 * Manages construction types and materials taxonomies for relational select components.
 * Populates data based on select_relations.jpg structure with hardcoded relationships.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TaxonomyManager {

    /**
     * Initialize taxonomy hooks
     */
    public static function init(): void {
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] init() called - registering hooks');
        add_action('init', [self::class, 'register_taxonomies'], 5);
        add_action('init', [self::class, 'populate_taxonomies'], 15);
    }

    /**
     * Register custom taxonomies for construction types and materials
     */
    public static function register_taxonomies(): void {
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] register_taxonomies() called');
        
        // Register construction types taxonomy (English slug, Czech labels)
        $result1 = register_taxonomy('construction_type', ['realizace'], [
            'labels' => [
                'name' => 'Typy konstrukcí',
                'singular_name' => 'Typ konstrukce',
                'menu_name' => 'Typy konstrukcí',
                'all_items' => 'Všechny typy konstrukcí',
                'parent_item' => 'Nadřazený typ konstrukce',
                'parent_item_colon' => 'Nadřazený typ konstrukce:',
                'new_item_name' => 'Název nového typu konstrukce',
                'add_new_item' => 'Přidat nový typ konstrukce',
                'edit_item' => 'Upravit typ konstrukce',
                'update_item' => 'Aktualizovat typ konstrukce',
                'view_item' => 'Zobrazit typ konstrukce',
                'separate_items_with_commas' => 'Oddělte typy konstrukcí čárkami',
                'add_or_remove_items' => 'Přidat nebo odebrat typy konstrukcí',
                'choose_from_most_used' => 'Vyberte z nejpoužívanějších',
                'popular_items' => 'Oblíbené typy konstrukcí',
                'search_items' => 'Hledat typy konstrukcí',
                'not_found' => 'Nenalezeno',
                'no_terms' => 'Žádné typy konstrukcí',
                'items_list' => 'Seznam typů konstrukcí',
                'items_list_navigation' => 'Navigace seznamu typů konstrukcí'
            ],
            'hierarchical' => false,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_in_rest' => true,
            'rest_base' => 'construction-types',
            'query_var' => true,
            'rewrite' => [
                'slug' => 'construction-type',
                'with_front' => true,
                'hierarchical' => false
            ],
            'meta_box_cb' => false, // Disable meta box since we use ACF
        ]);

        // Register construction materials taxonomy (English slug, Czech labels)
        $result2 = register_taxonomy('construction_material', ['realizace'], [
            'labels' => [
                'name' => 'Stavební materiály',
                'singular_name' => 'Stavební materiál',
                'menu_name' => 'Stavební materiály',
                'all_items' => 'Všechny stavební materiály',
                'parent_item' => 'Nadřazený stavební materiál',
                'parent_item_colon' => 'Nadřazený stavební materiál:',
                'new_item_name' => 'Název nového stavebního materiálu',
                'add_new_item' => 'Přidat nový stavební materiál',
                'edit_item' => 'Upravit stavební materiál',
                'update_item' => 'Aktualizovat stavební materiál',
                'view_item' => 'Zobrazit stavební materiál',
                'separate_items_with_commas' => 'Oddělte stavební materiály čárkami',
                'add_or_remove_items' => 'Přidat nebo odebrat stavební materiály',
                'choose_from_most_used' => 'Vyberte z nejpoužívanějších',
                'popular_items' => 'Oblíbené stavební materiály',
                'search_items' => 'Hledat stavební materiály',
                'not_found' => 'Nenalezeno',
                'no_terms' => 'Žádné stavební materiály',
                'items_list' => 'Seznam stavebních materiálů',
                'items_list_navigation' => 'Navigace seznamu stavebních materiálů'
            ],
            'hierarchical' => false,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_in_rest' => true,
            'rest_base' => 'construction-materials',
            'query_var' => true,
            'rewrite' => [
                'slug' => 'construction-material',
                'with_front' => true,
                'hierarchical' => false
            ],
            'meta_box_cb' => false, // Disable meta box since we use ACF
        ]);
        
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Taxonomy registration results:', [
            'construction_type' => is_wp_error($result1) ? $result1->get_error_message() : 'success',
            'construction_material' => is_wp_error($result2) ? $result2->get_error_message() : 'success'
        ]);
    }

    /**
     * Populate taxonomies with data from select_relations.jpg
     */
    public static function populate_taxonomies(): void {
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] populate_taxonomies() called');
        
        // Only populate once
        if (get_option('realizace_taxonomies_populated')) {
            \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Taxonomies already populated, skipping');
            return;
        }
        
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Starting taxonomy population');

        // Construction types data from select_relations.jpg
        $construction_types = [
            1 => 'Šikmá střecha – zateplení nad krokvemi – systém TOPROCK',
            2 => 'Šikmá střecha – zateplení mezi a pod krokvemi',
            3 => 'Střop pod nevytápěnou půdou (se střechou bez tepelné izolace)',
            4 => 'Plovoucí akustické podlahy',
            5 => 'Podlahy na polštářích nad terénem',
            6 => 'Příčky, předstěny',
            7 => 'Rámová konstrukce dřevostavby',
            8 => 'Provětrávaná fasáda',
            9 => 'Kontaktní fasáda',
            10 => 'Krby'
        ];

        // Materials data from select_relations.jpg (unique materials only)
        $materials = [
            'ROCKTON PREMIUM' => 'ROCKTON PREMIUM',
            'SUPERROCK PREMIUM' => 'SUPERROCK PREMIUM', 
            'ROCKTON SUPER' => 'ROCKTON SUPER',
            'SUPERROCK' => 'SUPERROCK',
            'ROCKMIN PLUS' => 'ROCKMIN PLUS',
            'TOPROCK PREMIUM' => 'TOPROCK PREMIUM',
            'TOPROCK SUPER' => 'TOPROCK SUPER',
            'TOPROCK PLUS' => 'TOPROCK PLUS',
            'ROCKMIN' => 'ROCKMIN',
            'GRANROCK PREMIUM' => 'GRANROCK PREMIUM',
            'GRANROCK SUPER' => 'GRANROCK SUPER',
            'STEPROCK SUPER' => 'STEPROCK SUPER',
            'STEPROCK PLUS' => 'STEPROCK PLUS',
            'SYSTÉM AKUFLOOR' => 'SYSTÉM AKUFLOOR',
            'FRONTROCK SUPER' => 'FRONTROCK SUPER',
            'FRONTROCK PLUS' => 'FRONTROCK PLUS',
            'FRONTROCK S' => 'FRONTROCK S',
            'FRONTROCK L' => 'FRONTROCK L',
            'FIREROCK' => 'FIREROCK'
        ];

        // Insert construction types (or get existing ones)
        $type_term_map = [];
        foreach ($construction_types as $id => $name) {
            $term = wp_insert_term($name, 'construction_type');
            if (!is_wp_error($term)) {
                $type_term_map[$id] = $term['term_id'];
                \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Inserted construction type:', [
                    'id' => $id, 
                    'name' => $name, 
                    'term_id' => $term['term_id']
                ]);
            } else {
                // Term already exists, get its ID
                $existing_term = get_term_by('name', $name, 'construction_type');
                if ($existing_term) {
                    $type_term_map[$id] = $existing_term->term_id;
                    \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Using existing construction type:', [
                        'id' => $id, 
                        'name' => $name, 
                        'term_id' => $existing_term->term_id
                    ]);
                } else {
                    \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Failed to insert construction type:', [
                        'id' => $id, 
                        'name' => $name, 
                        'error' => $term->get_error_message()
                    ]);
                }
            }
        }

        // Insert materials (or get existing ones)
        $material_term_map = [];
        foreach ($materials as $slug => $name) {
            $term = wp_insert_term($name, 'construction_material');
            if (!is_wp_error($term)) {
                $material_term_map[$slug] = $term['term_id'];
            } else {
                // Term already exists, get its ID
                $existing_term = get_term_by('name', $name, 'construction_material');
                if ($existing_term) {
                    $material_term_map[$slug] = $existing_term->term_id;
                }
            }
        }

        // Create relationships based on select_relations.jpg structure
        $relationships = [
            // Types 1, 2, 3, 5, 8 use ROCKTON/SUPERROCK/TOPROCK variants
            1 => ['ROCKTON PREMIUM', 'SUPERROCK PREMIUM', 'ROCKTON SUPER', 'SUPERROCK', 'ROCKMIN PLUS'],
            2 => ['ROCKTON PREMIUM', 'SUPERROCK PREMIUM', 'TOPROCK PREMIUM', 'ROCKTON SUPER', 'SUPERROCK', 'TOPROCK SUPER', 'ROCKMIN PLUS', 'TOPROCK PLUS', 'ROCKMIN'],
            3 => ['ROCKTON PREMIUM', 'SUPERROCK PREMIUM', 'TOPROCK PREMIUM', 'ROCKTON SUPER', 'SUPERROCK', 'TOPROCK SUPER', 'ROCKMIN PLUS', 'TOPROCK PLUS', 'ROCKMIN', 'GRANROCK PREMIUM', 'GRANROCK SUPER'],
            4 => ['STEPROCK SUPER', 'STEPROCK PLUS', 'SYSTÉM AKUFLOOR'],
            5 => ['ROCKTON PREMIUM', 'SUPERROCK PREMIUM', 'TOPROCK PREMIUM', 'ROCKTON SUPER', 'SUPERROCK', 'TOPROCK SUPER', 'ROCKMIN PLUS', 'TOPROCK PLUS', 'ROCKMIN', 'GRANROCK PREMIUM', 'GRANROCK SUPER'],
            6 => ['ROCKTON SUPER', 'SUPERROCK', 'ROCKMIN'],
            7 => ['SUPERROCK PREMIUM'],
            8 => ['ROCKTON PREMIUM', 'SUPERROCK PREMIUM', 'ROCKTON SUPER', 'SUPERROCK'],
            9 => ['FRONTROCK SUPER', 'FRONTROCK PLUS', 'FRONTROCK S', 'FRONTROCK L'],
            10 => ['FIREROCK']
        ];

        // Store relationships as term meta
        foreach ($relationships as $type_id => $material_slugs) {
            if (isset($type_term_map[$type_id])) {
                $material_ids = [];
                foreach ($material_slugs as $material_slug) {
                    if (isset($material_term_map[$material_slug])) {
                        $material_ids[] = $material_term_map[$material_slug];
                    }
                }

                if (!empty($material_ids)) {
                    $wordpress_term_id = $type_term_map[$type_id];
                    update_term_meta($wordpress_term_id, 'allowed_materials', $material_ids);
                    \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Stored relationship', [
                        'original_type_id' => $type_id,
                        'wordpress_term_id' => $wordpress_term_id,
                        'material_count' => count($material_ids),
                        'material_ids' => $material_ids
                    ]);
                }
            }
        }

        // Mark as populated
        update_option('realizace_taxonomies_populated', true);

        error_log('[REALIZACE:TAXONOMY] Populated ' . count($construction_types) . ' construction types and ' . count($materials) . ' materials with relationships');
    }

    /**
     * Get allowed materials for construction type(s)
     *
     * @param array $construction_type_ids Array of construction type term IDs
     * @return array Array of material term objects
     */
    public static function get_allowed_materials(array $construction_type_ids): array {
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] get_allowed_materials called', [
            'input_ids' => $construction_type_ids
        ]);
        
        if (empty($construction_type_ids)) {
            \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Empty construction type IDs, returning empty array');
            return [];
        }

        $all_material_ids = [];

        foreach ($construction_type_ids as $type_id) {
            $material_ids = get_term_meta($type_id, 'allowed_materials', true);
            \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Processing construction type', [
                'type_id' => $type_id,
                'material_ids' => $material_ids,
                'is_array' => is_array($material_ids)
            ]);
            
            if (is_array($material_ids)) {
                $all_material_ids = array_merge($all_material_ids, $material_ids);
            }
        }

        // Remove duplicates
        $all_material_ids = array_unique($all_material_ids);
        
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Merged material IDs', [
            'all_material_ids' => $all_material_ids,
            'count' => count($all_material_ids)
        ]);

        if (empty($all_material_ids)) {
            \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] No material IDs found, returning empty array');
            return [];
        }

        // Check if construction_material taxonomy exists before querying
        if (!taxonomy_exists('construction_material')) {
            \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] construction_material taxonomy does not exist during get_allowed_materials, registering now');
            self::register_taxonomies();
        }
        
        // Double-check after registration
        if (!taxonomy_exists('construction_material')) {
            \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] construction_material taxonomy STILL does not exist after registration!');
            return [];
        }
        
        // Get term objects
        $materials = get_terms([
            'taxonomy' => 'construction_material',
            'include' => $all_material_ids,
            'hide_empty' => false,
        ]);
        
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] get_terms result for materials', [
            'is_wp_error' => is_wp_error($materials),
            'is_array' => is_array($materials),
            'count' => is_array($materials) ? count($materials) : 0,
            'materials' => is_array($materials) ? array_map(function($term) { 
                return ['id' => $term->term_id, 'name' => $term->name]; 
            }, $materials) : $materials
        ]);

        return is_array($materials) ? $materials : [];
    }

    /**
     * Get all construction types
     *
     * @return array Array of construction type term objects
     */
    public static function get_construction_types(): array {
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] get_construction_types() called');
        
        // Check if taxonomy exists, if not, try to register it
        if (!taxonomy_exists('construction_type')) {
            \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] construction_type taxonomy does not exist, registering now');
            self::register_taxonomies();
        }
        
        // Check again after registration attempt
        if (!taxonomy_exists('construction_type')) {
            \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] construction_type taxonomy still does not exist after registration attempt!');
            return [];
        }
        
        // Check if data was populated, if not, populate it
        $populated = get_option('realizace_taxonomies_populated');
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Taxonomies populated option:', ['populated' => $populated]);
        
        if (!$populated) {
            \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Data not populated, populating now');
            self::populate_taxonomies();
        }
        
        // Check if relationships exist for sample term
        $sample_term = get_terms(['taxonomy' => 'construction_type', 'number' => 1]);
        if (!empty($sample_term)) {
            $sample_materials = get_term_meta($sample_term[0]->term_id, 'allowed_materials', true);
            if (empty($sample_materials)) {
                \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] Relationships missing, repopulating once');
                delete_option('realizace_taxonomies_populated');
                self::populate_taxonomies();
            }
        }
        
        $types = get_terms([
            'taxonomy' => 'construction_type',
            'hide_empty' => false,
        ]);
        
        \MistrFachman\Services\DebugLogger::log('[TaxonomyManager] get_terms result:', [
            'is_wp_error' => is_wp_error($types),
            'is_array' => is_array($types),
            'count' => is_array($types) ? count($types) : 0,
            'types' => is_array($types) ? array_map(function($term) { 
                return ['id' => $term->term_id, 'name' => $term->name]; 
            }, $types) : $types
        ]);

        return is_array($types) ? $types : [];
    }

    /**
     * Get all materials
     *
     * @return array Array of material term objects
     */
    public static function get_all_materials(): array {
        $materials = get_terms([
            'taxonomy' => 'construction_material',
            'hide_empty' => false,
        ]);

        return is_array($materials) ? $materials : [];
    }

    /**
     * Reset taxonomies (for development/testing)
     */
    public static function reset_taxonomies(): void {
        // Delete all terms
        $construction_types = get_terms(['taxonomy' => 'construction_type', 'hide_empty' => false]);
        foreach ($construction_types as $term) {
            wp_delete_term($term->term_id, 'construction_type');
        }

        $materials = get_terms(['taxonomy' => 'construction_material', 'hide_empty' => false]);
        foreach ($materials as $term) {
            wp_delete_term($term->term_id, 'construction_material');
        }

        // Reset population flag
        delete_option('realizace_taxonomies_populated');

        error_log('[REALIZACE:TAXONOMY] Reset taxonomies and data');
    }
}
