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
	$my_account_url = '/muj-ucet';
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
	// Enqueue admin-specific CSS (contains grid layout, collapsible sections, etc.)
	wp_enqueue_style(
		'theme-admin-css',
		get_stylesheet_directory_uri() . '/style.css',
		['admin-bar'],
		wp_get_theme()->get('Version')
	);

	// Register built admin JavaScript bundle
	$admin_js_asset_file = get_stylesheet_directory() . '/build/js/admin.asset.php';
	$admin_js_asset = file_exists($admin_js_asset_file) ? include $admin_js_asset_file : array('dependencies' => array(), 'version' => '1.0.0');

	wp_enqueue_script(
		'theme-admin-js',
		get_stylesheet_directory_uri() . '/build/js/admin.js',
		$admin_js_asset['dependencies'],
		$admin_js_asset['version'],
		true
	);
}


/**
 * Login Page Content Output Buffering
 *
 * Intercepts and modifies login page content based on user status
 * without JavaScript DOM manipulation to avoid blink effects.
 */
add_action('template_redirect', 'lwp_init_login_content_modification');
function lwp_init_login_content_modification() {
    // Debug: check current page
    if (is_admin()) {
        return; // Don't run in admin
    }

    // Check if we're on the login page (try different approaches)
    $is_login_page = is_page('prihlaseni') ||
                     is_page('přihlášení') ||
                     strpos($_SERVER['REQUEST_URI'], '/prihlaseni') !== false ||
                     strpos($_SERVER['REQUEST_URI'], '/prihlaseni/') !== false;

    if (!$is_login_page) {
        return;
    }

    // Start output buffering
    ob_start('lwp_modify_login_content');
}

function lwp_modify_login_content($buffer) {
    // Check if we're on the activation step (form#lwp_activate is visible)
    $is_activation_step = strpos($buffer, 'id="lwp_activate"') !== false && strpos($buffer, 'style="display: block;"') !== false;

    if ($is_activation_step) {
        // Handle activation form content (no toggle link needed here)
        return lwp_modify_activation_content($buffer);
    }

    // Define content variations for login form
    $content_variants = [
        'logged_out' => [
            'title' => 'Registrujte se, získáte tak přístup k výhodám, které mají jen Mistři.',
            'subtitle' => 'Stačí zadat číslo a kód vám hned přistane v mobilu.',
            'button' => 'Poslat ověřovací kód',
            'disclaimer' => 'Telefonní číslo slouží k ověření totožnosti. Registrací souhlasíte s podmínkami programu.',
            'toggle_link' => 'Registraci už mám - Chci se přihlásit'
        ],
        'existing_user' => [
            'title' => 'Zadejte své telefonní číslo a v appce jste raz dva',
            'subtitle' => 'Pošleme vám jednorázový kód pro rychlé přihlášení.',
            'button' => 'Poslat ověřovací kód',
            'disclaimer' => 'Telefonní číslo slouží k ověření totožnosti.',
            'toggle_link' => 'Ještě nemám registraci - Registrovat se'
        ]
    ];

    // Determine which content to use based on has_account cookie
    $is_existing_user = isset($_COOKIE['has_account']) && $_COOKIE['has_account'] === 'true';
    $content = $is_existing_user ? $content_variants['existing_user'] : $content_variants['logged_out'];

    // Add SVG icon to button text
    $button_with_icon = $content['button'] . ' <img src="data:image/svg+xml,%3Csvg%20width%3D%2227%22%20height%3D%2226%22%20viewBox%3D%220%200%2027%2026%22%20fill%3D%22none%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22m25.5%201-8.4%2024-4.8-10.8M25.5%201l-24%208.4%2010.8%204.8M25.5%201%2012.3%2014.2%22%20stroke%3D%22%23fff%22%20stroke-width%3D%221.333%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E" style="width: 24px; height: 24px; display: inline-flex;" alt="">';

    // Replace title
    $buffer = preg_replace(
        '/<div class="lh1">.*?<\/div>/s',
        '<div class="lh1">' . esc_html($content['title']) . '</div>',
        $buffer
    );

    // Replace subtitle
    $buffer = preg_replace(
        '/<label class="lwp_labels"[^>]*>.*?<\/label>/s',
        '<label class="lwp_labels" for="lwp_username">' . esc_html($content['subtitle']) . '</label>',
        $buffer
    );

    // Replace button text and add icon
    $buffer = preg_replace(
        '/<button class="submit_button auth_phoneNumber" type="submit">\s*Submit\s*<\/button>/s',
        '<button class="submit_button auth_phoneNumber" type="submit">' . $button_with_icon . '</button>',
        $buffer
    );

    // Replace entire terms section with proper structure like OTP
    $disclaimer_with_link = 'Telefonní číslo slouží k ověření totožnosti. Registrací souhlasíte s <a href="#">podmínkami programu</a>.';
    $terms_html = '<div class="accept_terms_and_conditions">' . $disclaimer_with_link . '</div>';
    
    // Remove the entire broken terms section and replace with clean version
    $buffer = preg_replace(
        '/<div class="accept_terms_and_conditions">.*?<\/div>/s',
        $terms_html,
        $buffer
    );

    // Add toggle link after submit button
    $toggle_link_html = '<a href="#" class="lwp_change_pn">' . esc_html($content['toggle_link']) . '</a>';
    $buffer = preg_replace(
        '/(<button class="submit_button auth_phoneNumber"[^>]*>.*?<\/button>)/s',
        '$1' . $toggle_link_html,
        $buffer
    );

    return $buffer;
}

/**
 * Modify activation form content based on Figma design
 */
function lwp_modify_activation_content($buffer) {
    // Activation form content from Figma
    $activation_content = [
        'title' => 'Zadejte kód, který jsme vám poslali',
        'subtitle' => 'Nic vám nepřišlo? Stačí kliknout a <a href="#" class="lwp_didnt_r_c">kód pošleme znovu</a>.',
        'button' => 'Registrovat',
        'resend_button' => 'Znovu odeslat kód',
        'login_link' => 'Registraci už mám - Chci se přihlásit',
        'disclaimer' => 'Telefonní číslo slouží k ověření totožnosti. Registrací souhlasíte s <a href="#">podmínkami programu</a>.'
    ];

    // Replace activation form title
    $buffer = preg_replace(
        '/<div class="lh1">.*?<\/div>/s',
        '<div class="lh1">' . esc_html($activation_content['title']) . '</div>',
        $buffer
    );

    // Replace activation form subtitle with inline link
    $buffer = preg_replace(
        '/<label class="lwp_labels"[^>]*>.*?<\/label>/s',
        '<label class="lwp_labels" for="lwp_username">' . $activation_content['subtitle'] . '</label>',
        $buffer
    );

    // Replace main button text (Registrovat)
    $buffer = preg_replace(
        '/<button class="submit_button auth_secCode"[^>]*>.*?<\/button>/s',
        '<button class="submit_button auth_secCode">' . esc_html($activation_content['button']) . '</button>',
        $buffer
    );

    // Replace resend button text
    $buffer = preg_replace(
        '/<button class="submit_button lwp_didnt_r_c[^"]*"[^>]*>.*?<\/button>/s',
        '<button class="submit_button lwp_didnt_r_c lwp_disable firebase" type="button">' . esc_html($activation_content['resend_button']) . '</button>',
        $buffer
    );

    // Replace "Change phone number" link with "Registraci už mám - Chci se přihlásit"
    $buffer = preg_replace(
        '/<a class="lwp_change_pn"[^>]*>.*?<\/a>/s',
        '<a class="lwp_change_pn" href="#">' . esc_html($activation_content['login_link']) . '</a>',
        $buffer
    );

    // Add proper terms disclaimer after the form (remove inline style and add link)
    $terms_html = '<div class="activation-terms"><span class="activation-terms-text">' . $activation_content['disclaimer'] . '</span></div>';
    $buffer = preg_replace(
        '/<\/form>/i',
        $terms_html . '</form>',
        $buffer
    );

    // Hide recaptcha status message
    $buffer = preg_replace(
        '/<p class="status">running recaptcha\.\.\.<\/p>/s',
        '<p class="status" style="display: none;">running recaptcha...</p>',
        $buffer
    );

    return $buffer;
}

/**
 * Set cookie for returning users on successful login
 * This allows us to show appropriate login text on /prihlaseni page
 */
add_action('wp_login', 'set_returning_user_cookie');
add_action('wp_authenticate', 'set_returning_user_cookie_on_auth');
add_action('wp_signon', 'set_returning_user_cookie_on_signon');
add_action('init', 'set_returning_user_cookie_on_login_check');

function set_returning_user_cookie() {
    setcookie('has_account', 'true', time() + YEAR_IN_SECONDS, '/');
}

function set_returning_user_cookie_on_auth() {
    if (is_user_logged_in()) {
        setcookie('has_account', 'true', time() + YEAR_IN_SECONDS, '/');
    }
}

function set_returning_user_cookie_on_signon() {
    if (is_user_logged_in()) {
        setcookie('has_account', 'true', time() + YEAR_IN_SECONDS, '/');
    }
}

function set_returning_user_cookie_on_login_check() {
    // Check if user just logged in this request
    if (is_user_logged_in() && !isset($_COOKIE['has_account'])) {
        setcookie('has_account', 'true', time() + YEAR_IN_SECONDS, '/');
    }
}

/**
 * Application Bootstrap
 *
 * Load the main application bootstrap file.
 */
require_once get_stylesheet_directory() . '/src/bootstrap.php';

