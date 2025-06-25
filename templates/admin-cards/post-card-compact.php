<?php
/**
 * Compact Post Card Template
 * 
 * Simplified post card for approved items
 * 
 * @var \WP_Post $post Post object
 * @var string $post_type Post type slug
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$awarded_points = (int)get_post_meta($post->ID, "_{$post_type}_points_awarded", true);
?>

<div class="<?php echo esc_attr($post_type); ?>-item-compact" data-post-id="<?php echo esc_attr((string)$post->ID); ?>">
    <div class="compact-content">
        <div class="compact-title">
            <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a>
        </div>
        <div class="compact-meta">
            <span class="compact-date"><?php echo esc_html(mysql2date('j.n.Y', $post->post_date)); ?></span>
            <?php if ($awarded_points > 0): ?>
                <span class="compact-points"><?php echo esc_html((string)$awarded_points); ?> bodů</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="compact-actions">
        <button type="button"
                class="button button-small action-reject"
                data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                data-action="reject">Zrušit</button>
    </div>
</div>