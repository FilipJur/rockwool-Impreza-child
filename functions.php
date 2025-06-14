<?php
/**
 * Impreza-child Theme functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package impreza-child
 */
include_once("includes/leadhub.php");

/**
 * Enqueue scripts and styles.
 */
add_action('wp_enqueue_scripts', 'impreza_parent_theme_enqueue_styles');
function impreza_parent_theme_enqueue_styles()
{
	wp_enqueue_style('impreza-style', get_template_directory_uri() . '/style.css');
	wp_enqueue_style(
		'impreza-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		['impreza-style']
	);
}

// Centralized enqueue for child theme custom assets
add_action('wp_enqueue_scripts', 'impreza_child_enqueue_custom_assets');
function impreza_child_enqueue_custom_assets()
{	// Enqueue built JavaScript bundle
	$js_asset_file = get_stylesheet_directory() . '/build/js/main.asset.php';
	$js_asset = file_exists($js_asset_file) ? include $js_asset_file : array('dependencies' => array(), 'version' => '1.0.0');

	wp_enqueue_script(
		'theme-main-js',
		get_stylesheet_directory_uri() . '/build/js/main.js',
		$js_asset['dependencies'],
		$js_asset['version'],
		true
	);

}


/**
 * Application Bootstrap
 *
 * Load the main application bootstrap file.
 */
require_once get_stylesheet_directory() . '/src/bootstrap.php';

