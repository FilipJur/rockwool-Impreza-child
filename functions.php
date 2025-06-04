<?php
/**
 * Impreza-child Theme functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package impreza-child
 */


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
{
	wp_enqueue_style(
		'file-upload-enhanced-css',
		get_stylesheet_directory_uri() . '/src/css/file-upload-enhanced.css',
		array('impreza-child-style'),
		'1.0.0'
	);

	// Enqueue built JavaScript bundle
	$js_asset_file = get_stylesheet_directory() . '/build/js/main.asset.php';
	$js_asset = file_exists($js_asset_file) ? include $js_asset_file : array('dependencies' => array(), 'version' => '1.0.0');
	
	wp_enqueue_script(
		'theme-main-js',
		get_stylesheet_directory_uri() . '/build/js/main.js',
		$js_asset['dependencies'],
		$js_asset['version'],
		true
	);

	// Add any WordPress AJAX data if needed
	wp_localize_script('theme-main-js', 'wpAjax', array(
		'ajaxurl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('wp_ajax_nonce'),
	));
}



/**
 * Ultra-jednoduchá funkce pro zjištění klíče typu bodů myCred pro WooCommerce.
 */
if (!function_exists('poc_get_mycred_woo_point_type')) {
	function poc_get_mycred_woo_point_type()
	{
		// Pokusí se použít preferovanou funkci myCred, pokud existuje
		if (function_exists('mycred_get_woo_point_type')) {
			$point_type = mycred_get_woo_point_type();
			if (!empty($point_type)) {
				return $point_type;
			}
		}
		// Záložní metoda: přímé čtení nastavení brány myCred
		$gateway_settings = get_option('woocommerce_mycred_settings');
		return isset($gateway_settings['point_type']) ? $gateway_settings['point_type'] : (defined('MYCRED_DEFAULT_TYPE_KEY') ? MYCRED_DEFAULT_TYPE_KEY : 'mycred_default');
	}
}

/**
 * Pomocná funkce pro získání efektivní bodové ceny produktu.
 * Předpokládáme, že $product->get_price() vrací cenu v bodech.
 */
if (!function_exists('poc_get_product_effective_point_cost')) {
	function poc_get_product_effective_point_cost($product_object)
	{
		if (!$product_object instanceof WC_Product) {
			// Pokusíme se získat produktový objekt, pokud byl předán ID nebo jiný typ
			$product_object = wc_get_product($product_object);
		}
		if (!$product_object) {
			return null; // Produkt nebyl nalezen
		}
		$price = $product_object->get_price();
		// Vracíme float nebo null, pokud cena není platná
		return ($price !== '' && is_numeric($price)) ? floatval($price) : null;
	}
}

/**
 * Ultra-jednoduchá funkce pro získání celkové bodové hodnoty aktuálního košíku.
 */
if (!function_exists('poc_get_current_cart_points_total')) {
	function poc_get_current_cart_points_total()
	{
		// Zajistíme, že WooCommerce a košík jsou dostupné
		if (!function_exists('WC') || !WC()->cart) {
			return 0.0; // Vracíme float pro konzistenci
		}

		if (WC()->cart->is_empty()) {
			return 0.0;
		}

		$cart_total_points = 0.0;
		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			// Získáme produktový objekt z položky košíku
			$_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

			// Získáme bodovou cenu produktu pomocí naší pomocné funkce
			$product_cost_points = poc_get_product_effective_point_cost($_product);

			if (!is_null($product_cost_points) && isset($cart_item['quantity']) && $cart_item['quantity'] > 0) {
				$cart_total_points += ($product_cost_points * $cart_item['quantity']);
			}
		}
		return (float) $cart_total_points;
	}
}


/**
 * Ultra-jednoduchá kontrola, zda je produkt prodejný na základě bodů myCred,
 * s ohledem na aktuální obsah košíku.
 * Toto je hlavní filtr, který ovlivní prodejnost.
 */
// Přejmenoval jsem funkci a navázal na ni, aby bylo jasné, že jde o novou verzi
add_filter('woocommerce_is_purchasable', 'poc_mycred_check_product_affordability_with_cart', 10, 2);
add_filter('woocommerce_variation_is_purchasable', 'poc_mycred_check_product_affordability_with_cart', 10, 2);

if (!function_exists('poc_mycred_check_product_affordability_with_cart')) {
	function poc_mycred_check_product_affordability_with_cart($is_purchasable, $product)
	{
		// Pokud produkt již není prodejný z jiných důvodů (např. není na skladě), nic neměníme.
		if (!$is_purchasable) {
			return false;
		}

		// Pracujeme pouze pro přihlášené uživatele a pokud je myCred aktivní.
		if (!is_user_logged_in() || !function_exists('mycred') || !$product instanceof WC_Product) {
			return $is_purchasable;
		}

		$user_id = get_current_user_id();
		$point_type_key = poc_get_mycred_woo_point_type();

		if (empty($point_type_key)) {
			return $is_purchasable; // Nemůžeme určit typ bodů
		}

		$point_type_object = mycred($point_type_key);

		if (!$point_type_object || !is_object($point_type_object) || !method_exists($point_type_object, 'get_users_balance')) {
			return $is_purchasable; // Nemůžeme získat objekt typu bodů nebo nemá potřebnou metodu
		}

		$user_balance = (float) $point_type_object->get_users_balance($user_id);
		$product_cost_to_add = poc_get_product_effective_point_cost($product); // Cena produktu, který se snažíme přidat

		// Pokud se nepodařilo zjistit cenu produktu k přidání (např. je prázdná), nic neměníme
		if (is_null($product_cost_to_add)) {
			return $is_purchasable;
		}

		// Získáme aktuální celkovou bodovou hodnotu košíku
		$current_cart_total_points = poc_get_current_cart_points_total();

		// Vypočítáme potenciální celkovou cenu (košík + nový produkt)
		// Ujistíme se, že produkt_cost_to_add je nezáporný pro výpočet
		$potential_total_cost = $current_cart_total_points + ($product_cost_to_add >= 0 ? $product_cost_to_add : 0);

		// Pokud je potenciální celková cena vyšší než zůstatek uživatele, produkt není prodejný.
		// Také zkontrolujeme, zda $product_cost_to_add není záporný (což by nemělo nastat, ale pro jistotu).
		if ($product_cost_to_add >= 0 && $potential_total_cost > $user_balance) {
			// error_log("myCred PoC Blocked: User Balance: $user_balance, Product Cost to Add: $product_cost_to_add, Cart Total: $current_cart_total_points, Potential Total: $potential_total_cost");
			return false;
		}

		// Jinak ponecháme původní stav prodejnosti.
		return $is_purchasable;
	}
}

/**
 * Ultra-jednoduchá úprava tlačítka "Přidat do košíku" na stránkách archivu (např. obchod),
 * pokud produkt není prodejný (na základě filtru výše, který nyní zohledňuje i košík).
 */
add_filter('woocommerce_loop_add_to_cart_link', 'poc_mycred_loop_add_to_cart_link_text', 20, 3);

if (!function_exists('poc_mycred_loop_add_to_cart_link_text')) {
	function poc_mycred_loop_add_to_cart_link_text($html_link, $product, $args)
	{
		// Bezpečnostní kontrola, zda je $product platný objekt WC_Product
		if (!$product instanceof WC_Product) {
			return $html_link;
		}

		// Pokud náš předchozí filtr označil produkt jako neprodejný (nyní s ohledem na košík).
		if (!$product->is_purchasable()) {
			// Cílíme na jednoduché, externí nebo seskupené produkty.
			// Variabilní produkty mají typicky tlačítko "Select options", které necháváme být pro jednoduchost PoC.
			if ($product->is_type('simple') || $product->is_type('external') || $product->is_type('grouped')) {
				$message = esc_html__('Not enough points (inc. cart)', 'woocommerce'); // Jasnější zpráva
				return sprintf(
					'<span class="button disabled" style="background-color:#e0e0e0 !important; color:#888888 !important; cursor:not-allowed !important; padding: %s !important; display:inline-block; text-align:center;">%s</span>',
					isset($args['attributes']['style']) ? esc_attr($args['attributes']['style']) : '0.618em 1em',
					$message
				);
			}
		}
		return $html_link; // Vrátíme původní HTML tlačítka/odkazu.
	}
}

