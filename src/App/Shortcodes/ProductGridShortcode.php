<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;

/**
 * Product Grid Shortcode Component
 *
 * Displays a grid of WooCommerce products with myCred balance filtering.
 * Supports various display options and integrates with the cart-aware balance system.
 *
 * Usage: [mycred_product_grid balance_filter="affordable" columns="3" limit="12"]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class ProductGridShortcode extends ShortcodeBase
{
    private ProductService $product_service;

    public function __construct(Manager $mycred_manager, ProductService $product_service) {
        parent::__construct($mycred_manager);
        $this->product_service = $product_service;
    }

	protected array $default_attributes = [
		'balance_filter' => 'all',          // all, affordable, unavailable
		'limit' => '12',                    // Number of products to show
		'category' => '',                   // Product category slug
		'order' => 'ASC',                   // ASC or DESC
		'orderby' => 'title',               // title, date, price, menu_order, rand
		'show_balance_info' => 'true',      // Show balance information
		'class' => ''                       // Additional CSS classes
	];

	public function get_tag(): string
	{
		return 'mycred_product_grid';
	}

	public function get_description(): string
	{
		return 'Zobrazí mřížku produktů s možností filtrování podle dostupných bodů';
	}

	public function get_example(): string
	{
		return '[mycred_product_grid balance_filter="affordable" columns="3" limit="12"]';
	}

	protected function render(array $attributes, ?string $content = null): string
	{
		if (!function_exists('wc_get_products')) {
			return $this->render_error('WooCommerce není aktivní');
		}

		$query_args = [
			'limit'    => (int) $attributes['limit'],
			'category' => !empty($attributes['category']) ? [$attributes['category']] : [],
			'orderby'  => $attributes['orderby'],
			'order'    => $attributes['order'],
		];

		// Get pre-filtered data directly from the intelligent service
		$products = $this->product_service->get_products_filtered_by_balance(
			$attributes['balance_filter'],
			$query_args
		);

		if (empty($products)) {
			return $attributes['balance_filter'] === 'all' ?
				$this->render_no_products($attributes) :
				$this->render_no_affordable_products($attributes);
		}

		// Prepare data for template
		$template_data = [
			'products' => $products,
			'attributes' => $attributes,
			'user_balance' => $this->mycred_manager->get_user_balance(),
			'available_points' => $this->mycred_manager->get_available_points(),
			'wrapper_classes' => $this->get_wrapper_classes($attributes)
		];

		return $this->render_builtin_grid($template_data);
	}


	/**
	 * Render built-in product grid (fallback when no template exists)
	 *
	 * @param array $data Template data
	 * @return string Rendered HTML
	 */
	private function render_builtin_grid(array $data): string
	{
		$products = $data['products'];
		$attributes = $data['attributes'];

		$html = sprintf('<div class="%s">', esc_attr($data['wrapper_classes']));

		// Add balance info if enabled
		if ($attributes['show_balance_info'] === 'true') {
			$html .= $this->render_balance_info($data);
		}

		// Build grid classes - only add columns class if explicitly set
		$grid_classes = ['mycred-products-grid'];
		if (!empty($attributes['columns'])) {
			$columns = (int) $attributes['columns'];
			$grid_classes[] = 'columns-' . $columns;
		}

		$html .= sprintf('<div class="%s">', esc_attr(implode(' ', $grid_classes)));

		foreach ($products as $product) {
			$html .= $this->render_product_item($product, $attributes);
		}

		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Render balance information section
	 *
	 * @param array $data Template data
	 * @return string Rendered HTML
	 */
	private function render_balance_info(array $data): string
	{
		$user_balance = $data['user_balance'];
		$available_points = $data['available_points'];
		$filter = $data['attributes']['balance_filter'];

		$html = '<div class="mycred-balance-info">';

		switch ($filter) {
			case 'affordable':
				$html .= sprintf(
					'<p class="balance-message">Zobrazeny produkty, které si můžete dovolit (dostupné body: %s)</p>',
					number_format($available_points)
				);
				break;

			case 'unavailable':
				$html .= sprintf(
					'<p class="balance-message">Zobrazeny produkty mimo váš dosah (dostupné body: %s)</p>',
					number_format($available_points)
				);
				break;

			default:
				$html .= sprintf(
					'<p class="balance-message">Váš zůstatek: %s bodů | Dostupné: %s bodů</p>',
					number_format($user_balance),
					number_format($available_points)
				);
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render individual product item
	 *
	 * @param \WC_Product $product Product object
	 * @param array $attributes Shortcode attributes
	 * @return string Rendered HTML
	 */
	private function render_product_item(\WC_Product $product, array $attributes): string
	{
		$can_afford = $this->mycred_manager->can_afford_product($product);
		$price = $product->get_price();
		$price_display = $price ? number_format((float) $price) . ' bodů' : 'Zdarma';

		$item_classes = [
			'mycred-product-item',
			$can_afford ? 'affordable' : 'unavailable'
		];

		$html = sprintf('<div class="%s">', esc_attr(implode(' ', $item_classes)));
		$html .= '<div class="product-image">';

		// Product image
		if ($product->get_image_id()) {
			$html .= wp_get_attachment_image($product->get_image_id(), 'woocommerce_thumbnail');
		} else {
			$html .= '<div class="placeholder-image">Bez obrázku</div>';
		}

		$html .= '</div>';
		$html .= '<div class="product-info">';
		$html .= sprintf(
			'<h3 class="product-title"><a href="%s">%s</a></h3>',
			esc_url($product->get_permalink()),
			esc_html($product->get_name())
		);

		$html .= sprintf(
			'<div class="product-price %s">%s</div>',
			$can_afford ? 'affordable' : 'unavailable',
			esc_html($price_display)
		);

		if (!$can_afford) {
			$html .= '<div class="affordability-message">Nedostatek bodů</div>';
		}

		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Render no products message
	 *
	 * @param array $attributes Shortcode attributes
	 * @return string Rendered HTML
	 */
	private function render_no_products(array $attributes): string
	{
		$wrapper_classes = $this->get_wrapper_classes($attributes);

		return sprintf(
			'<div class="%s"><p class="no-products">Žádné produkty nebyly nalezeny.</p></div>',
			esc_attr($wrapper_classes)
		);
	}

	/**
	 * Render no affordable products message
	 *
	 * @param array $attributes Shortcode attributes
	 * @return string Rendered HTML
	 */
	private function render_no_affordable_products(array $attributes): string
	{
		$wrapper_classes = $this->get_wrapper_classes($attributes);
		$filter_text = $attributes['balance_filter'] === 'affordable' ? 'dostupné' : 'nedostupné';

		return sprintf(
			'<div class="%s"><p class="no-products">Žádné %s produkty nebyly nalezeny.</p></div>',
			esc_attr($wrapper_classes),
			esc_html($filter_text)
		);
	}

	/**
	 * Override wrapper classes to include balance filter context
	 *
	 * @param array $attributes Shortcode attributes
	 * @return string CSS classes
	 */
	protected function get_wrapper_classes(array $attributes): string {
		$classes = [
			'mycred-shortcode',
			'mycred-' . str_replace('_', '-', $this->get_tag()),
			'filter-' . $attributes['balance_filter']
		];

		if (!empty($attributes['class'])) {
			$classes[] = sanitize_html_class($attributes['class']);
		}

		return implode(' ', $classes);
	}

}
