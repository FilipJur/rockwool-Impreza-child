<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

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
    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
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

		// Component data (like React props)
		$user_balance = $this->ecommerce_manager->get_user_balance();
		$available_points = $this->ecommerce_manager->get_available_points();
		$wrapper_classes = $this->get_wrapper_classes($attributes);
		$show_balance_info = $attributes['show_balance_info'] === 'true';
		$columns = !empty($attributes['columns']) ? (int) $attributes['columns'] : null;

		// JSX-like template rendering
		ob_start(); ?>
		<div class="<?= esc_attr($wrapper_classes) ?>">
			<?php if ($show_balance_info): ?>
				<div class="mycred-balance-info">
					<?php switch ($attributes['balance_filter']):
						case 'affordable': ?>
							<p class="balance-message">
								Zobrazeny produkty, které si můžete dovolit (dostupné body: <?= number_format($available_points) ?>)
							</p>
							<?php break;
						case 'unavailable': ?>
							<p class="balance-message">
								Zobrazeny produkty mimo váš dosah (dostupné body: <?= number_format($available_points) ?>)
							</p>
							<?php break;
						default: ?>
							<p class="balance-message">
								Váš zůstatek: <?= number_format($user_balance) ?> bodů | Dostupné: <?= number_format($available_points) ?> bodů
							</p>
							<?php break;
					endswitch; ?>
				</div>
			<?php endif; ?>

			<div class="mycred-products-grid<?= $columns ? ' columns-' . $columns : '' ?>">
				<?php foreach ($products as $product):
					$can_afford = $this->ecommerce_manager->can_afford_product($product);
					$price = $product->get_price();
					$price_display = $price ? number_format((float) $price) . ' bodů' : 'Zdarma';
				?>
					<div class="mycred-product-item <?= $can_afford ? 'affordable' : 'unavailable' ?>">
						<div class="product-image">
							<?php if ($product->get_image_id()): ?>
								<?= wp_get_attachment_image($product->get_image_id(), 'woocommerce_thumbnail') ?>
							<?php else: ?>
								<div class="placeholder-image">Bez obrázku</div>
							<?php endif; ?>
						</div>
						<div class="product-info">
							<h3 class="product-title">
								<a href="<?= esc_url($product->get_permalink()) ?>">
									<?= esc_html($product->get_name()) ?>
								</a>
							</h3>
							<div class="product-price <?= $can_afford ? 'affordable' : 'unavailable' ?>">
								<?= esc_html($price_display) ?>
							</div>
							<?php if (!$can_afford): ?>
								<div class="affordability-message">Nedostatek bodů</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php return ob_get_clean();
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

		ob_start(); ?>
		<div class="<?= esc_attr($wrapper_classes) ?>">
			<p class="no-products">Žádné produkty nebyly nalezeny.</p>
		</div>
		<?php return ob_get_clean();
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

		ob_start(); ?>
		<div class="<?= esc_attr($wrapper_classes) ?>">
			<p class="no-products">Žádné <?= esc_html($filter_text) ?> produkty nebyly nalezeny.</p>
		</div>
		<?php return ob_get_clean();
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
