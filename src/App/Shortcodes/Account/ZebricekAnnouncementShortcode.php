<?php

declare(strict_types=1);

namespace MistrFachman\Shortcodes\Account;

use MistrFachman\Shortcodes\ShortcodeBase;
use MistrFachman\MyCred\ECommerce\Manager;
use MistrFachman\Services\ProductService;
use MistrFachman\Services\UserService;

/**
 * Zebricek Announcement Shortcode Component
 *
 * Simple competition announcement text following Figma design.
 * Displays contest title and duration information.
 *
 * Usage: [zebricek_announcement]
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZebricekAnnouncementShortcode extends ShortcodeBase
{
    protected array $default_attributes = [
        'class' => ''
    ];

    public function get_tag(): string
    {
        return 'zebricek_announcement';
    }

    protected function render(array $attributes, ?string $content = null): string
    {
        // Prepare template data
        $template_data = [
            'attributes' => $attributes,
            'wrapper_classes' => $this->get_wrapper_classes($attributes)
        ];

        return $this->render_announcement($template_data);
    }

    /**
     * Render announcement display
     */
    private function render_announcement(array $data): string
    {
        extract($data);
        
        ob_start(); ?>
        <div class="<?= esc_attr($wrapper_classes) ?> zebricek-announcement">
            <div class="zebricek-announcement__content">
                <h2 class="zebricek-announcement__title">
                    Mistr FACHMAN 2025
                </h2>
                <p class="zebricek-announcement__subtitle">
                    Soutěž o hodnotné ceny běží až do 31. 12. 2025
                </p>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * Override wrapper classes for announcement
     */
    protected function get_wrapper_classes(array $attributes): string
    {
        $classes = [
            'mycred-shortcode',
            'mycred-' . str_replace('_', '-', $this->get_tag())
        ];

        if (!empty($attributes['class'])) {
            $classes[] = sanitize_html_class($attributes['class']);
        }

        return implode(' ', $classes);
    }
}