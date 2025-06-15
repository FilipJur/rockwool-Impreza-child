<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;

/**
 * Shortcode Manager Class
 *
 * Handles auto-discovery, registration, and management of all shortcode components.
 * Provides a centralized system for registering shortcodes with WordPress.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ShortcodeManager {

    private Manager $ecommerce_manager;
    private ProductService $product_service;
    private array $registered_shortcodes = [];

    public function __construct(Manager $ecommerce_manager, ProductService $product_service) {
        $this->ecommerce_manager = $ecommerce_manager;
        $this->product_service = $product_service;
        $this->discover_and_register_shortcodes();
    }

    /**
     * Auto-discover and register all shortcode classes
     */
    private function discover_and_register_shortcodes(): void {
        $shortcode_directory = get_stylesheet_directory() . '/src/App/Shortcodes/';
        
        if (!is_dir($shortcode_directory)) {
            mycred_debug('Shortcode directory not found', $shortcode_directory, 'shortcode_manager', 'warning');
            return;
        }

        // Get all PHP files in the shortcodes directory
        $files = glob($shortcode_directory . '*.php');
        
        foreach ($files as $file) {
            $this->try_register_shortcode_from_file($file);
        }

        mycred_debug('Shortcode registration complete', [
            'registered_count' => count($this->registered_shortcodes),
            'shortcodes' => array_keys($this->registered_shortcodes)
        ], 'shortcode_manager', 'info');
    }

    /**
     * Try to register a shortcode from a file
     * 
     * @param string $file_path Path to PHP file
     */
    private function try_register_shortcode_from_file(string $file_path): void {
        $filename = basename($file_path, '.php');
        
        // Skip base classes and manager
        if (in_array($filename, ['ShortcodeBase', 'ShortcodeManager'], true)) {
            return;
        }

        // Build class name from filename
        $class_name = 'MistrFachman\\Shortcodes\\' . $filename;
        
        try {
            if (class_exists($class_name)) {
                $reflection = new \ReflectionClass($class_name);
                
                // Only register concrete classes that extend ShortcodeBase
                if (!$reflection->isAbstract() && $reflection->isSubclassOf(ShortcodeBase::class)) {
                    $shortcode_instance = new $class_name($this->ecommerce_manager, $this->product_service);
                    $this->register_shortcode($shortcode_instance);
                }
            }
        } catch (\Exception $e) {
            mycred_debug('Failed to register shortcode from file', [
                'file' => $file_path,
                'class' => $class_name,
                'error' => $e->getMessage()
            ], 'shortcode_manager', 'error');
        }
    }

    /**
     * Register a shortcode instance with WordPress
     * 
     * @param ShortcodeBase $shortcode Shortcode instance
     */
    private function register_shortcode(ShortcodeBase $shortcode): void {
        $tag = $shortcode->get_tag();
        
        if (shortcode_exists($tag)) {
            mycred_debug('Shortcode tag already exists', [
                'tag' => $tag,
                'class' => get_class($shortcode)
            ], 'shortcode_manager', 'warning');
            return;
        }

        // Register with WordPress
        add_shortcode($tag, [$shortcode, 'handle_shortcode']);
        
        // Store in our registry
        $this->registered_shortcodes[$tag] = $shortcode;
        
        mycred_debug('Shortcode registered', [
            'tag' => $tag,
            'class' => get_class($shortcode)
        ], 'shortcode_manager', 'info');
    }

    /**
     * Manually register a shortcode instance
     * 
     * @param ShortcodeBase $shortcode Shortcode instance
     */
    public function add_shortcode(ShortcodeBase $shortcode): void {
        $this->register_shortcode($shortcode);
    }

    /**
     * Get all registered shortcodes
     * 
     * @return array Array of registered shortcode instances
     */
    public function get_registered_shortcodes(): array {
        return $this->registered_shortcodes;
    }

    /**
     * Get a specific shortcode instance by tag
     * 
     * @param string $tag Shortcode tag
     * @return ShortcodeBase|null Shortcode instance or null if not found
     */
    public function get_shortcode(string $tag): ?ShortcodeBase {
        return $this->registered_shortcodes[$tag] ?? null;
    }

    /**
     * Check if a shortcode is registered
     * 
     * @param string $tag Shortcode tag
     * @return bool True if shortcode is registered
     */
    public function has_shortcode(string $tag): bool {
        return isset($this->registered_shortcodes[$tag]);
    }

    /**
     * Unregister a shortcode
     * 
     * @param string $tag Shortcode tag
     */
    public function remove_shortcode(string $tag): void {
        if ($this->has_shortcode($tag)) {
            remove_shortcode($tag);
            unset($this->registered_shortcodes[$tag]);
            
            mycred_debug('Shortcode unregistered', ['tag' => $tag], 'shortcode_manager', 'info');
        }
    }

    /**
     * Get shortcode usage documentation for admin
     * 
     * @return array Array of shortcode documentation
     */
    public function get_shortcode_documentation(): array {
        $documentation = [];
        
        foreach ($this->registered_shortcodes as $tag => $shortcode) {
            $documentation[$tag] = [
                'tag' => $tag,
                'class' => get_class($shortcode),
                'description' => $this->get_shortcode_description($shortcode),
                'example' => $this->get_shortcode_example($shortcode)
            ];
        }
        
        return $documentation;
    }

    /**
     * Get description for a shortcode (can be overridden by shortcode classes)
     * 
     * @param ShortcodeBase $shortcode Shortcode instance
     * @return string Description
     */
    private function get_shortcode_description(ShortcodeBase $shortcode): string {
        if (method_exists($shortcode, 'get_description')) {
            return $shortcode->get_description();
        }
        
        return sprintf('Shortcode poskytovaný třídou %s', get_class($shortcode));
    }

    /**
     * Get usage example for a shortcode (can be overridden by shortcode classes)
     * 
     * @param ShortcodeBase $shortcode Shortcode instance
     * @return string Usage example
     */
    private function get_shortcode_example(ShortcodeBase $shortcode): string {
        if (method_exists($shortcode, 'get_example')) {
            return $shortcode->get_example();
        }
        
        return sprintf('[%s]', $shortcode->get_tag());
    }

    /**
     * Add admin hooks for shortcode management
     */
    public function init_admin_hooks(): void {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
        }
    }

    /**
     * Add admin menu for shortcode documentation
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'tools.php',
            'MyCred Shortcodes',
            'MyCred Shortcodes',
            'manage_options',
            'mycred-shortcodes',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Render admin page for shortcode documentation
     */
    public function render_admin_page(): void {
        $documentation = $this->get_shortcode_documentation();
        
        echo '<div class="wrap">';
        echo '<h1>MyCred Shortcodes</h1>';
        echo '<p>Dostupné shortcodes pro zobrazení MyCred obsahu:</p>';
        
        if (empty($documentation)) {
            echo '<p>Žádné shortcodes nejsou registrovány.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Shortcode</th><th>Popis</th><th>Příklad použití</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($documentation as $doc) {
                echo '<tr>';
                echo '<td><code>[' . esc_html($doc['tag']) . ']</code></td>';
                echo '<td>' . esc_html($doc['description']) . '</td>';
                echo '<td><code>' . esc_html($doc['example']) . '</code></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }
}