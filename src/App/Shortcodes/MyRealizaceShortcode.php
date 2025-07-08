<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes;

/**
 * My Realizace Shortcode - User Submission View
 *
 * Provides the user-facing view of their realizace submissions.
 * Shows submission status, rejection reasons, and points awarded.
 *
 * Usage: [my_realizace]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class MyRealizaceShortcode extends ShortcodeBase
{

	protected array $default_attributes = [
		'posts_per_page' => '6',
		'show_content' => 'false',
		'enable_pagination' => 'true',
	];

	/**
	 * Register AJAX hooks for this shortcode
	 */
	public function register_ajax_hooks(): void
	{
		add_action('wp_ajax_my_realizace_load_page', [$this, 'handle_load_page']);
		add_action('wp_ajax_nopriv_my_realizace_load_page', [$this, 'handle_load_page']);
	}

	/**
	 * Get the shortcode tag name
	 *
	 * @return string
	 */
	public function get_tag(): string
	{
		return 'my_realizace';
	}

	/**
	 * Render the shortcode output
	 *
	 * @param array $attributes Shortcode attributes
	 * @param string|null $content Shortcode content
	 * @return string Rendered HTML output
	 */
	protected function render(array $attributes, ?string $content = null): string
	{
		// Check if user is logged in
		if (!is_user_logged_in()) {
			return $this->render_login_message();
		}

		$user_id = get_current_user_id();

		// Query user's realizace posts
		$query_args = $this->get_query_args($attributes, $user_id);
		$query = new \WP_Query($query_args);

		if (!$query->have_posts()) {
			return $this->render_no_posts_message();
		}

		$output = $this->render_posts_container($query, $attributes);

		wp_reset_postdata();

		return $output;
	}

	/**
	 * Get query arguments for posts
	 *
	 * @param array $attributes Shortcode attributes
	 * @param int $user_id User ID
	 * @param int $offset Pagination offset
	 * @return array Query arguments
	 */
	private function get_query_args(array $attributes, int $user_id, int $offset = 0): array
	{
		return [
			'post_type' => 'realization',
			'author' => $user_id,
			'post_status' => ['pending', 'publish', 'rejected'],
			'posts_per_page' => (int) $attributes['posts_per_page'],
			'offset' => $offset,
			'orderby' => 'date',
			'order' => 'DESC',
		];
	}

	/**
	 * Handle AJAX load page request
	 */
	public function handle_load_page(): void
	{
		// Verify nonce
		if (!check_ajax_referer('my_realizace_nonce', 'nonce', false)) {
			wp_send_json_error(['message' => 'Invalid nonce']);
			return;
		}

		// Check if user is logged in
		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
			return;
		}

		$user_id = get_current_user_id();
		$page = (int) ($_POST['page'] ?? 1);
		$posts_per_page = (int) ($_POST['posts_per_page'] ?? 10);
		$offset = ($page - 1) * $posts_per_page;

		$attributes = [
			'posts_per_page' => $posts_per_page,
			'show_content' => sanitize_text_field($_POST['show_content'] ?? 'false'),
		];

		$query_args = $this->get_query_args($attributes, $user_id, $offset);
		$query = new \WP_Query($query_args);

		if (!$query->have_posts()) {
			wp_send_json_success([
				'html' => '',
				'has_more' => false,
				'total_pages' => 0,
			]);
			return;
		}

		$html = $this->render_posts_list($query, $attributes);
		$has_more = $query->max_num_pages > $page;

		wp_reset_postdata();

		wp_send_json_success([
			'html' => $html,
			'has_more' => $has_more,
			'total_pages' => $query->max_num_pages,
		]);
	}

	/**
	 * Render login message for non-logged-in users
	 *
	 * @return string HTML output
	 */
	private function render_login_message(): string
	{
		$login_url = wp_login_url(get_permalink());

		ob_start(); ?>
		<div class="bg-blue-50 border border-blue-200 p-4 text-center">
			<p class="text-blue-700">
				Pro zobrazení vašich realizací se musíte
				<a href="<?php echo esc_url($login_url); ?>" class="text-blue-800 underline hover:text-blue-900">přihlásit</a>.
			</p>
		</div>
		<?php return ob_get_clean();
	}

	/**
	 * Render message when user has no posts
	 *
	 * @return string HTML output
	 */
	private function render_no_posts_message(): string
	{
		ob_start(); ?>
		<div class="bg-gray-50 border border-gray-200 p-6 text-center">
			<p class="text-gray-600">Zatím jste nepřidali žádné realizace.</p>
		</div>
		<?php return ob_get_clean();
	}

	/**
	 * Render the main posts container with pagination
	 *
	 * @param \WP_Query $query Query object
	 * @param array $attributes Shortcode attributes
	 * @return string HTML output
	 */
	private function render_posts_container(\WP_Query $query, array $attributes): string
	{
		$nonce = wp_create_nonce('my_realizace_nonce');
		$enable_pagination = $attributes['enable_pagination'] === 'true';

		ob_start(); ?>
		<div class="my-realizace-shortcode"
			 data-nonce="<?php echo esc_attr($nonce); ?>"
			 data-posts-per-page="<?php echo esc_attr($attributes['posts_per_page']); ?>"
			 data-show-content="<?php echo esc_attr($attributes['show_content']); ?>"
			 data-enable-pagination="<?php echo esc_attr($attributes['enable_pagination']); ?>"
			 data-total-pages="<?php echo esc_attr($query->max_num_pages); ?>">

			<div class="my-realizace-posts grid grid-cols-1 md:grid-cols-2 gap-4">
				<?php echo $this->render_posts_list($query, $attributes); ?>
			</div>

			<?php if ($enable_pagination && $query->max_num_pages > 1) : ?>
				<div class="my-realizace-pagination mt-6 text-center">
					<!-- Pagination will be rendered by JavaScript -->
				</div>
				<div class="my-realizace-loading hidden text-center mt-4">
					<div class="inline-flex items-center px-4 py-2 text-sm">
						Načítání...
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php return ob_get_clean();
	}

	/**
	 * Render the list of posts
	 *
	 * @param \WP_Query $query Query object
	 * @param array $attributes Shortcode attributes
	 * @return string HTML output
	 */
	private function render_posts_list(\WP_Query $query, array $attributes): string
	{
		// Reset post index counter for new page and store total posts
		wp_cache_set('my_realizace_post_index', 0, 'shortcode');
		wp_cache_set('my_realizace_total_posts', $query->post_count, 'shortcode');

		ob_start(); ?>
		<?php while ($query->have_posts()) : $query->the_post(); ?>
			<?php echo $this->render_single_post($attributes); ?>
		<?php endwhile; ?>
		<?php return ob_get_clean();
	}

	/**
	 * Render a single realizace post
	 *
	 * @param array $attributes Shortcode attributes
	 * @return string HTML output
	 */
	private function render_single_post(array $attributes): string
	{
		$post_id = get_the_ID();
		$status = get_post_status();
		$status_label = $this->get_status_label($status);
		$status_colors = $this->get_status_colors($status);
		$title = get_the_title();
		$date = get_the_date();
		$column_span = $this->get_post_column_span($attributes);

		ob_start(); ?>
		<div class="bg-white border <?php echo esc_attr($status_colors['border']); ?> p-4 shadow-sm <?php echo $column_span; ?>" data-debug-span="<?php echo esc_attr($column_span); ?>">
			<h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo esc_html($title); ?></h3>

			<div class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium <?php echo esc_attr($status_colors['badge']); ?> mb-2">
				<?php echo esc_html($status_label); ?>
			</div>

			<div class="text-sm text-gray-500 mb-2">
				Přidáno: <?php echo esc_html($date); ?>
			</div>

			<?php if ($status === 'publish') : ?>
				<?php $points = \MistrFachman\Realizace\RealizaceFieldService::getPoints($post_id); ?>
				<?php if ($points > 0) : ?>
					<div class="bg-green-50 border border-green-200 p-2 text-sm text-green-800 mb-2">
						Získané body: <strong><?php echo esc_html($points); ?></strong>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ($status === 'rejected') : ?>
				<?php $rejection_reason = \MistrFachman\Realizace\RealizaceFieldService::getRejectionReason($post_id); ?>
				<?php if ($rejection_reason) : ?>
					<div class="bg-red-50 border border-red-200 p-3 text-sm">
						<strong class="text-red-800">Důvod zamítnutí:</strong><br class="mb-1">
						<span class="text-red-700"><?php echo wp_kses_post($rejection_reason); ?></span>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ($attributes['show_content'] === 'true') : ?>
				<?php $content_preview = wp_trim_words(get_the_content(), 30, '...'); ?>
				<div class="text-sm text-gray-600 mt-3 pt-3 border-t border-gray-200">
					<?php echo esc_html($content_preview); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php return ob_get_clean();
	}

	/**
	 * Get localized status label
	 *
	 * @param string $status Post status
	 * @return string Localized label
	 */
	private function get_status_label(string $status): string
	{
		return match ($status) {
			'publish' => 'Schváleno',
			'pending' => 'Čeká na schválení',
			'rejected' => 'Odmítnuto',
			'draft' => 'Koncept',
			default => $status
		};
	}

	/**
	 * Get Tailwind colors for status
	 *
	 * @param string $status Post status
	 * @return array Border and badge color classes
	 */
	private function get_status_colors(string $status): array
	{
		return match ($status) {
			'publish' => [
				'border' => 'border-green-200',
				'badge' => 'bg-green-100 text-green-800'
			],
			'pending' => [
				'border' => 'border-yellow-200',
				'badge' => 'bg-yellow-100 text-yellow-800'
			],
			'rejected' => [
				'border' => 'border-red-200',
				'badge' => 'bg-red-100 text-red-800'
			],
			'draft' => [
				'border' => 'border-gray-200',
				'badge' => 'bg-gray-100 text-gray-800'
			],
			default => [
				'border' => 'border-gray-200',
				'badge' => 'bg-gray-100 text-gray-800'
			]
		};
	}

	/**
	 * Get dynamic column span class for posts
	 *
	 * @param array $attributes Shortcode attributes
	 * @return string CSS class for column span
	 */
	private function get_post_column_span(array $attributes): string
	{
		$total_posts = wp_cache_get('my_realizace_total_posts', 'shortcode') ?: 1;
		$post_index = wp_cache_get('my_realizace_post_index', 'shortcode') ?: 0;
		$current_post = $post_index + 1;

		// Increment post index for next call
		wp_cache_set('my_realizace_post_index', $current_post, 'shortcode');

		// Logic: 1 item = 2 columns, 2 items = 1 column each, 3 items = 1+1+2, etc.
		// When odd number of posts, the last item spans 2 columns
		if ($total_posts % 2 === 1 && $current_post === $total_posts) {
			// Last item of odd count spans full width
			return 'md:col-span-2';
		}

		// All other items get 1 column
		return '';
	}

	/**
	 * Override sanitize_attributes to handle specific attributes
	 *
	 * @param array $attributes Attributes to sanitize
	 * @return array Sanitized attributes
	 */
	protected function sanitize_attributes(array $attributes): array
	{
		$sanitized = [];

		foreach ($attributes as $key => $value) {
			$sanitized[$key] = match ($key) {
				'posts_per_page' => (int) $value,
				'show_content' => in_array($value, ['true', 'false'], true) ? $value : 'false',
				'enable_pagination' => in_array($value, ['true', 'false'], true) ? $value : 'false',
				default => sanitize_text_field($value)
			};
		}

		return $sanitized;
	}
}
