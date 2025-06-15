<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

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

	protected array $default_attributes = [
		'balance_filter' => 'all',          // all, affordable, unavailable
		'columns' => '3',                   // Number of columns in grid
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

		// Get products based on attributes
		$products = $this->get_products($attributes);

		if (empty($products)) {
			return $this->render_no_products($attributes);
		}

		// Filter products by balance if needed
		$filtered_products = $this->filter_products_by_balance($products, $attributes['balance_filter']);

		if (empty($filtered_products) && $attributes['balance_filter'] !== 'all') {
			return $this->render_no_affordable_products($attributes);
		}

		// Prepare data for template
		$template_data = [
			'products' => $filtered_products,
			'attributes' => $attributes,
			'user_balance' => $this->mycred_manager->get_user_balance(),
			'available_points' => $this->mycred_manager->get_available_points(),
			'wrapper_classes' => $this->get_wrapper_classes($attributes)
		];

		// Try to render with template, fallback to built-in rendering
		$template_output = $this->render_template('product-grid', $template_data);

		if ($template_output && !str_contains($template_output, 'Template not found')) {
			return $template_output;
		}

		return $this->render_builtin_grid($template_data);
	}

	/**
	 * Get products based on shortcode attributes
	 *
	 * @param array $attributes Validated shortcode attributes
	 * @return array Array of WC_Product objects
	 */
	private function get_products(array $attributes): array
	{
		$args = [
			'status' => 'publish',
			'limit' => (int) $attributes['limit'],
			'orderby' => $attributes['orderby'],
			'order' => $attributes['order'],
			'return' => 'objects'
		];

		// Add category filter if specified
		if (!empty($attributes['category'])) {
			$args['category'] = [$attributes['category']];
		}

		try {
			return wc_get_products($args);
		} catch (\Exception $e) {
			mycred_debug('Error fetching products for shortcode', [
				'attributes' => $attributes,
				'error' => $e->getMessage()
			], 'product_grid_shortcode', 'error');

			return [];
		}
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
		$columns = (int) $attributes['columns'];

		$html = sprintf('<div class="%s">', esc_attr($data['wrapper_classes']));

		// Add balance info if enabled
		if ($attributes['show_balance_info'] === 'true') {
			$html .= $this->render_balance_info($data);
		}

		$html .= sprintf('<div class="mycred-products-grid columns-%d">', $columns);

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
	 * Add CSS for product grid styling
	 */
	public function add_default_styles(): void
	{
		$css = '
        .mycred-product-grid {
            margin: 20px 0;
        }

        .mycred-balance-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .mycred-products-grid {
            display: grid;
            gap: 20px;
        }

        .mycred-products-grid.columns-1 { grid-template-columns: 1fr; }
        .mycred-products-grid.columns-2 { grid-template-columns: repeat(2, 1fr); }
        .mycred-products-grid.columns-3 { grid-template-columns: repeat(3, 1fr); }
        .mycred-products-grid.columns-4 { grid-template-columns: repeat(4, 1fr); }

        .mycred-product-item {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            background: #fff;
        }

        .mycred-product-item.unavailable {
            opacity: 0.6;
        }

        .mycred-product-item .product-image img {
            width: 100%;
            height: auto;
        }

        .mycred-product-item .placeholder-image {
            background: #f0f0f0;
            padding: 40px;
            text-align: center;
            color: #666;
        }

        .mycred-product-item .product-price.unavailable {
            color: #999;
        }

        .mycred-product-item .affordability-message {
            color: #d63384;
            font-size: 0.9em;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .mycred-products-grid {
                grid-template-columns: 1fr !important;
            }
        }
        ';

		wp_add_inline_style('woocommerce-general', $css);
	}
}
