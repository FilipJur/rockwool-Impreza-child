<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;
use MistrFachman\Services\IconHelper;

/**
 * Product Grid Shortcode Component
 *
 * Displays a grid of WooCommerce products following Figma design specifications.
 * Supports dynamic header text and button states based on user's affordability.
 *
 * Usage: [mycred_product_grid balance_filter="dynamic" limit="6"]
 *
 * Attributes:
 * - balance_filter: "dynamic" (default) - shows unavailable products if user can't afford all, available if they can
 *                   "available", "affordable", "strictly_available" - shows products user can afford
 *                   "unavailable", "strictly_unavailable" - shows products user cannot afford
 * - limit: Number of products to display (default: 12, applied after filtering)
 * - category: Product category slug to filter by (default: empty)
 * - orderby: Sort field - "price" (default), "title", "date", "menu_order", "rand"
 * - order: Sort direction - "ASC" (default), "DESC"
 * - header: Custom header text (overrides dynamic header based on affordability)
 * - class: Additional CSS classes
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
    // String constants to eliminate hardcoded values
    private const HEADER_AFFORD_ALL = 'Zasloužený výběr z toho nejlepšího';
    private const HEADER_DEFAULT_GOAL = 'Čím víc bodů, tím lepší odměny';
    private const BUTTON_TEXT_AFFORD = 'Vybrat odměnu';
    private const BUTTON_TEXT_UNAVAILABLE = 'Zobrazit detail';
    private const NO_PRODUCTS_HEADER = 'Žádné produkty';
    private const NO_PRODUCTS_MESSAGE = 'Žádné produkty nebyly nalezeny.';
    private const POINTS_SUFFIX = ' b.';
    private const FREE_TEXT = 'Zdarma';
    private const PLACEHOLDER_IMAGE_TEXT = 'Bez obrázku';

    public function __construct(Manager $ecommerce_manager, ProductService $product_service, UserService $user_service) {
        parent::__construct($ecommerce_manager, $product_service, $user_service);
    }

	protected array $default_attributes = [
		'balance_filter' => 'dynamic',      // strictly_available, strictly_unavailable, dynamic
		'limit' => '12',                    // Number of products to show
		'category' => '',                   // Product category slug
		'order' => 'ASC',                   // ASC or DESC (used for manual overrides)
		'orderby' => 'price',               // title, date, price, menu_order, rand (default: price)
		'class' => '',                      // Additional CSS classes
		'header' => ''                      // Custom header text (overrides dynamic header)
	];

	public function get_tag(): string
	{
		return 'mycred_product_grid';
	}

	public function get_description(): string
	{
		return 'Zobrazí mřížku produktů následující Figma design specifikace';
	}

	public function get_example(): string
	{
		return '[mycred_product_grid balance_filter="affordable" limit="6" header="Vaše dostupné odměny"]';
	}

	protected function render(array $attributes, ?string $content = null): string
	{
		if (!function_exists('wc_get_products')) {
			return $this->render_error('WooCommerce není aktivní');
		}

		$query_args = [
			'category' => !empty($attributes['category']) ? [$attributes['category']] : [],
			'orderby'  => $attributes['orderby'],
			'order'    => $attributes['order'],
		];

		// Get user context for intelligent product selection
		$user_id = get_current_user_id();
		$can_afford_everything = $this->product_service->can_user_afford_all_products($user_id);
		
		// Get products based on filter type
		$products = $this->get_filtered_products($attributes, $user_id, $query_args);

		// Apply limit after filtering
		if (!empty($products) && $attributes['limit'] > 0) {
			$products = array_slice($products, 0, (int) $attributes['limit']);
		}

		if (empty($products)) {
			return $this->render_no_products($attributes);
		}

		// Header text: custom override or dynamic based on user's ability to afford everything
		$header_text = !empty($attributes['header']) ? 
			$attributes['header'] : 
			($can_afford_everything ? 
				self::HEADER_AFFORD_ALL : 
				self::HEADER_DEFAULT_GOAL);
		
		$wrapper_classes = $this->get_wrapper_classes($attributes);

		// Simple affordability determination based on filter type (no complex logic)
		$is_set_affordable = in_array($attributes['balance_filter'], ['available', 'affordable', 'strictly_available']) ||
			($attributes['balance_filter'] === 'dynamic' && $can_afford_everything);
		
		// Template rendering following Figma design exactly
		ob_start(); ?>
		<div class="<?= esc_attr($wrapper_classes) ?>">
			<div class="products-header">
				<h2 class="products-title"><?= esc_html($header_text) ?></h2>
			</div>

			<div class="products-container">
				<?php foreach ($products as $product):
					$price = $product->get_price();
					$price_display = $price ? number_format((float) $price) . self::POINTS_SUFFIX : self::FREE_TEXT;
					$button_text = $is_set_affordable ? self::BUTTON_TEXT_AFFORD : self::BUTTON_TEXT_UNAVAILABLE;
				?>
					<div class="product-card">
						<div class="points-and-lock">
							<?php if (!$is_set_affordable): ?>
								<?= IconHelper::get_icon('lock') ?>
							<?php endif; ?>
							<span class="points-value"><?= esc_html($price_display) ?></span>
						</div>
						
						<div class="product-image">
							<?php if ($product->get_image_id()): ?>
								<?= wp_get_attachment_image($product->get_image_id(), 'woocommerce_thumbnail') ?>
							<?php else: ?>
								<div class="placeholder-image"><?= esc_html(self::PLACEHOLDER_IMAGE_TEXT) ?></div>
							<?php endif; ?>
						</div>
						
						<div class="product-details">
							<div class="product-name-and-description">
								<h3 class="product-name">
									<a href="<?= esc_url($product->get_permalink()) ?>">
										<?= esc_html($product->get_name()) ?>
									</a>
								</h3>
								<?php if ($product->get_short_description()): ?>
									<p class="product-description"><?= esc_html($product->get_short_description()) ?></p>
								<?php endif; ?>
							</div>
							
							<a href="<?= esc_url($product->get_permalink()) ?>" class="product-button <?= $is_set_affordable ? 'affordable' : 'unavailable' ?>">
								<?= esc_html($button_text) ?>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php return ob_get_clean();
	}

	/**
	 * Get filtered products using clean ProductService API (dumb consumer pattern)
	 */
	private function get_filtered_products(array $attributes, int $user_id, array $query_args): array
	{
		// Simple switch that delegates to explicit ProductService methods
		switch ($attributes['balance_filter']) {
			case 'available':
			case 'affordable':
			case 'strictly_available':
				return $this->product_service->get_affordable_products($user_id, $query_args);
				
			case 'unavailable':
			case 'strictly_unavailable':
				return $this->product_service->get_unaffordable_products($user_id, $query_args);
				
			case 'dynamic':
			default:
				// For dynamic filter, don't pass ordering attributes - let service decide
				$dynamic_query_args = $query_args;
				unset($dynamic_query_args['order'], $dynamic_query_args['orderby']);
				return $this->product_service->get_dynamic_product_selection($user_id, $dynamic_query_args);
		}
	}

	/**
	 * Render no products message
	 */
	private function render_no_products(array $attributes): string
	{
		$wrapper_classes = $this->get_wrapper_classes($attributes);

		ob_start(); ?>
		<div class="<?= esc_attr($wrapper_classes) ?>">
			<div class="products-header">
				<h2 class="products-title"><?= esc_html(self::NO_PRODUCTS_HEADER) ?></h2>
			</div>
			<p class="no-products"><?= esc_html(self::NO_PRODUCTS_MESSAGE) ?></p>
		</div>
		<?php return ob_get_clean();
	}

	/**
	 * Override wrapper classes to include balance filter context
	 */
	protected function get_wrapper_classes(array $attributes): string {
		$classes = [
			'product-grid',
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