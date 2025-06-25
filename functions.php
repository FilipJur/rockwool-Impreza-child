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
 * Enqueue scripts and styles for frontend only.
 * 
 * Note: Tailwind CSS is intentionally NOT loaded in admin to prevent 
 * class conflicts with WordPress core (e.g., .fixed class on admin tables).
 */
add_action('wp_enqueue_scripts', 'impreza_parent_theme_enqueue_styles');
function impreza_parent_theme_enqueue_styles()
{
	// Parent theme styles
	wp_enqueue_style('impreza-style', get_template_directory_uri() . '/style.css');
	
	// Tailwind utilities (frontend only - loaded first, lower priority)
	wp_enqueue_style(
		'tailwind-utilities',
		get_stylesheet_directory_uri() . '/tailwind.css',
		['impreza-style'],
		'1.0.0'
	);
	
	// Child theme custom styles (loaded last, higher priority)
	wp_enqueue_style(
		'impreza-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		['impreza-style', 'tailwind-utilities'],
		'1.0.0'
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

	// Inject user status and access control data for frontend
	impreza_child_inject_user_data();
}

/**
 * Inject user status and access control data for frontend JavaScript
 */
function impreza_child_inject_user_data()
{
	// Get user status using the Users domain service
	$users_manager = \MistrFachman\Users\Manager::get_instance();
	$user_status = $users_manager->user_service->get_user_registration_status();

	// Get My Account URL
	$my_account_url = '/';
	if (function_exists('wc_get_page_id') && wc_get_page_id('myaccount') > 0) {
		$my_account_url = wc_get_account_endpoint_url('');
	} else {
		$my_account_page = get_page_by_path('muj-ucet');
		if ($my_account_page) {
			$my_account_url = get_permalink($my_account_page->ID);
		}
	}

	// Get eshop URL - prioritize WooCommerce shop page
	$eshop_url = '/obchod';
	if (function_exists('wc_get_page_id') && wc_get_page_id('shop') > 0) {
		$eshop_url = get_permalink(wc_get_page_id('shop'));
	} else {
		$obchod_page = get_page_by_path('obchod');
		if ($obchod_page) {
			$eshop_url = get_permalink($obchod_page->ID);
		}
	}

	// Prepare data for JavaScript
	$js_data = array(
		'userStatus' => $user_status,
		'myAccountUrl' => $my_account_url,
		'eshopUrl' => $eshop_url,
		'isLoggedIn' => is_user_logged_in(),
		'currentUserId' => get_current_user_id(),
		'registrationPages' => array('prihlaseni', 'registrace'),
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('mistr_fachman_access_control'),
		'finalRegistrationFormId' => '292'
	);

	// Inject data into page
	wp_localize_script('theme-main-js', 'mistrFachman', $js_data);
}

// Admin-specific asset enqueuing (no Tailwind to avoid conflicts)
add_action('admin_enqueue_scripts', 'impreza_child_enqueue_admin_assets');
function impreza_child_enqueue_admin_assets()
{
	// Admin doesn't need frontend styles - only specific admin assets should be loaded here

	// Register built admin JavaScript bundle
	$admin_js_asset_file = get_stylesheet_directory() . '/build/js/admin.asset.php';
	$admin_js_asset = file_exists($admin_js_asset_file) ? include $admin_js_asset_file : array('dependencies' => array(), 'version' => '1.0.0');

	wp_register_script(
		'theme-admin-js',
		get_stylesheet_directory_uri() . '/build/js/admin.js',
		$admin_js_asset['dependencies'],
		$admin_js_asset['version'],
		true
	);
}


/**
 * Application Bootstrap
 *
 * Load the main application bootstrap file.
 */
require_once get_stylesheet_directory() . '/src/bootstrap.php';

