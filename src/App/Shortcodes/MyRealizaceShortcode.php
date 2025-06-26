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
		'posts_per_page' => '-1',
		'show_content' => 'false',
	];

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
		$query_args = [
			'post_type' => 'realization',
			'author' => $user_id,
			'post_status' => ['pending', 'publish', 'rejected'],
			'posts_per_page' => (int) $attributes['posts_per_page'],
			'orderby' => 'date',
			'order' => 'DESC',
		];

		$query = new \WP_Query($query_args);

		if (!$query->have_posts()) {
			return $this->render_no_posts_message();
		}

		$output = $this->render_posts_list($query, $attributes);

		wp_reset_postdata();

		return $output;
	}

	/**
	 * Render login message for non-logged-in users
	 *
	 * @return string HTML output
	 */
	private function render_login_message(): string
	{
		$login_url = wp_login_url(get_permalink());

		return "<div class='bg-blue-50 border border-blue-200 rounded-lg p-4 text-center'>
                <p class='text-blue-700'>Pro zobrazení vašich realizací se musíte <a href='{$login_url}' class='text-blue-800 underline hover:text-blue-900'>přihlásit</a>.</p>
            </div>";
	}

	/**
	 * Render message when user has no posts
	 *
	 * @return string HTML output
	 */
	private function render_no_posts_message(): string
	{
		return '<div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center		š">
            <p class="text-gray-600">Zatím jste nepřidali žádné realizace.</p>
        </div>';
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
		$output = '<div class="space-y-4">';

		while ($query->have_posts()) {
			$query->the_post();
			$output .= $this->render_single_post($attributes);
		}

		$output .= '</div>';

		return $output;
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
		$status_class = 'status-' . $status;

		$status_colors = $this->get_status_colors($status);
		$output = "<div class='bg-white border {$status_colors['border']} rounded-lg p-4 shadow-sm'>";

		// Title
		$title = get_the_title();
		$output .= "<h3 class='text-lg font-semibold text-gray-900 mb-2'>{$title}</h3>";

		// Status badge
		$output .= "<div class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$status_colors['badge']} mb-2'>{$status_label}</div>";

		// Date
		$date = get_the_date();
		$output .= "<div class='text-sm text-gray-500 mb-2'>Přidáno: {$date}</div>";

		// Points (only for published posts)
		if ($status === 'publish') {
			$points = \MistrFachman\Realizace\RealizaceFieldService::getPoints($post_id);
			if ($points > 0) {
				$output .= "<div class='bg-green-50 border border-green-200 rounded p-2 text-sm text-green-800 mb-2'>Získané body: <strong>{$points}</strong></div>";
			}
		}

		// Rejection reason (only for rejected posts)
		if ($status === 'rejected') {
			$rejection_reason = \MistrFachman\Realizace\RealizaceFieldService::getRejectionReason($post_id);
			if ($rejection_reason) {
				$clean_reason = wp_kses_post($rejection_reason);
				$output .= "<div class='bg-red-50 border border-red-200 rounded p-3 text-sm'>";
				$output .= "<strong class='text-red-800'>Důvod zamítnutí:</strong><br class='mb-1'>";
				$output .= "<span class='text-red-700'>{$clean_reason}</span>";
				$output .= '</div>';
			}
		}

		// Content preview (if enabled)
		if ($attributes['show_content'] === 'true') {
			$content_preview = wp_trim_words(get_the_content(), 30, '...');
			$output .= "<div class='text-sm text-gray-600 mt-3 pt-3 border-t border-gray-200'>{$content_preview}</div>";
		}

		$output .= '</div>';

		return $output;
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
				default => sanitize_text_field($value)
			};
		}

		return $sanitized;
	}
}
