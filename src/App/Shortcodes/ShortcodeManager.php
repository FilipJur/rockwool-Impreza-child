<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;
use MistrFachman\Services\UserStatusService;
use MistrFachman\Services\ZebricekDataService;
use MistrFachman\Services\ProjectStatusService;

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
    private UserService $user_service;
    private UserStatusService $user_status_service;
    private ZebricekDataService $zebricek_service;
    private ProjectStatusService $project_status_service;
    private array $registered_shortcodes = [];

    public function __construct(
        Manager $ecommerce_manager,
        ProductService $product_service,
        UserService $user_service,
        UserStatusService $user_status_service,
        ZebricekDataService $zebricek_service,
        ProjectStatusService $project_status_service
    ) {
        $this->ecommerce_manager = $ecommerce_manager;
        $this->product_service = $product_service;
        $this->user_service = $user_service;
        $this->user_status_service = $user_status_service;
        $this->zebricek_service = $zebricek_service;
        $this->project_status_service = $project_status_service;
    }

    /**
     * Initialize shortcodes (called explicitly from bootstrap)
     */
    public function init_shortcodes(): void {
        $this->discover_and_register_shortcodes();
        $this->discover_and_register_cf7_tags();
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

        // Get all PHP files in the main shortcodes directory
        $files = glob($shortcode_directory . '*.php');

        // Also get files from subdirectories (Account, etc.)
        $subdirectories = glob($shortcode_directory . '*/', GLOB_ONLYDIR);
        foreach ($subdirectories as $subdir) {
            $subdir_files = glob($subdir . '*.php');
            $files = array_merge($files, $subdir_files);
        }

        foreach ($files as $file) {
            $this->try_register_shortcode_from_file($file);
        }

        mycred_debug('Shortcode registration complete', [
            'registered_count' => count($this->registered_shortcodes),
            'shortcodes' => array_keys($this->registered_shortcodes)
        ], 'shortcode_manager', 'info');
    }

    /**
     * Auto-discover and register all CF7 form tag classes
     */
    private function discover_and_register_cf7_tags(): void {
        // Only register CF7 tags if Contact Form 7 is active
        if (!function_exists('wpcf7_add_form_tag')) {
            mycred_debug('Contact Form 7 not available, skipping CF7 tag registration', null, 'shortcode_manager', 'info');
            return;
        }

        $shortcode_directory = get_stylesheet_directory() . '/src/App/Shortcodes/';

        if (!is_dir($shortcode_directory)) {
            mycred_debug('Shortcode directory not found for CF7 tags', $shortcode_directory, 'shortcode_manager', 'warning');
            return;
        }

        // Get all PHP files in the shortcodes directory
        $files = glob($shortcode_directory . '*CF7Tag.php');

        foreach ($files as $file) {
            $this->try_register_cf7_tag_from_file($file);
        }

        mycred_debug('CF7 tag registration complete', [
            'cf7_tags_found' => count($files)
        ], 'shortcode_manager', 'info');
    }

    /**
     * Try to register a CF7 form tag from a file
     *
     * @param string $file_path Path to PHP file
     */
    private function try_register_cf7_tag_from_file(string $file_path): void {
        $filename = basename($file_path, '.php');

        // Build class name from filename
        $class_name = 'MistrFachman\\Shortcodes\\' . $filename;

        try {
            if (class_exists($class_name)) {
                $reflection = new \ReflectionClass($class_name);

                // Only register concrete classes that extend CF7FormTagBase
                if (!$reflection->isAbstract() && $reflection->isSubclassOf('MistrFachman\\Shortcodes\\CF7FormTagBase')) {
                    $cf7_tag_instance = new $class_name($this->ecommerce_manager, $this->product_service, $this->user_service);
                    $cf7_tag_instance->register_cf7_tag();

                    mycred_debug('CF7 tag registered', [
                        'tag' => $cf7_tag_instance->get_cf7_tag(),
                        'class' => $class_name
                    ], 'shortcode_manager', 'info');
                }
            }
        } catch (\Exception $e) {
            mycred_debug('Failed to register CF7 tag from file', [
                'file' => $file_path,
                'class' => $class_name,
                'error' => $e->getMessage()
            ], 'shortcode_manager', 'error');
        }
    }

    /**
     * Try to register a shortcode from a file
     *
     * @param string $file_path Path to PHP file
     */
    private function try_register_shortcode_from_file(string $file_path): void {
        $filename = basename($file_path, '.php');

        // Skip base classes and manager
        if (in_array($filename, ['ShortcodeBase', 'ShortcodeManager', 'CF7FormTagBase', 'CF7AssetManager'], true)) {
            return;
        }

        // Determine if file is in subdirectory to build correct namespace
        $shortcode_directory = get_stylesheet_directory() . '/src/App/Shortcodes/';
        $relative_path = str_replace($shortcode_directory, '', $file_path);
        $path_parts = pathinfo($relative_path);

        if ($path_parts['dirname'] !== '.') {
            // File is in subdirectory (e.g., Account/UserProgressGuideShortcode.php)
            $namespace_suffix = str_replace('/', '\\', $path_parts['dirname']);
            $class_name = 'MistrFachman\\Shortcodes\\' . $namespace_suffix . '\\' . $filename;
        } else {
            // File is in root shortcodes directory
            $class_name = 'MistrFachman\\Shortcodes\\' . $filename;
        }

        try {
            if (class_exists($class_name)) {
                $reflection = new \ReflectionClass($class_name);

                // Only register concrete classes that extend ShortcodeBase
                if (!$reflection->isAbstract() && $reflection->isSubclassOf(ShortcodeBase::class)) {
                    // Special handling for different shortcode dependency needs
                    if (str_contains($class_name, 'UserProgressGuide')) {
                        // UserProgressGuideShortcode needs ProjectStatusService
                        $shortcode_instance = new $class_name($this->ecommerce_manager, $this->product_service, $this->user_service, $this->project_status_service);
                    } elseif (str_contains($class_name, 'MyRealizace') || str_contains($class_name, 'MyFaktury')) {
                        // These shortcodes only need ProjectStatusService
                        $shortcode_instance = new $class_name($this->project_status_service);
                    } elseif (str_contains($class_name, 'Zebricek') || str_contains($class_name, 'Account')) {
                        // Other Account shortcodes need ZebricekDataService
                        $shortcode_instance = new $class_name($this->ecommerce_manager, $this->product_service, $this->user_service, $this->zebricek_service);
                    } elseif (str_contains($class_name, 'UserHeader')) {
                        // UserHeaderShortcode needs UserStatusService
                        $shortcode_instance = new $class_name($this->ecommerce_manager, $this->product_service, $this->user_service, $this->user_status_service);
                    } else {
                        // Default shortcodes
                        $shortcode_instance = new $class_name($this->ecommerce_manager, $this->product_service, $this->user_service);
                    }
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

        // Register AJAX hooks if the shortcode has any
        $shortcode->register_ajax_hooks();

        // Store in our registry
        $this->registered_shortcodes[$tag] = $shortcode;

        mycred_debug('Shortcode registered with AJAX hooks', [
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

}
