<?php
/**
 * Full Post Card Template
 * 
 * Detailed post card for pending/rejected items
 * 
 * @var \WP_Post $post Post object
 * @var string $post_type Post type slug
 * @var object $renderer The renderer instance for helper methods
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data from renderer methods
$assigned_points = $renderer->getPoints($post->ID);
$awarded_points = (int)get_post_meta($post->ID, "_{$post_type}_points_awarded", true);
$rejection_reason = $renderer->getRejectionReason($post->ID);
$gallery_images = $renderer->getGalleryImages($post->ID);
$field_data = $renderer->getDomainFieldData($post->ID);
$status_config = $renderer->get_status_config($post->post_status);
?>

<div class="<?php echo esc_attr($post_type); ?>-item" data-post-id="<?php echo esc_attr((string)$post->ID); ?>" data-status="<?php echo esc_attr($post->post_status); ?>">
    <div class="<?php echo esc_attr($post_type); ?>-header">
        <div class="<?php echo esc_attr($post_type); ?>-title">
            <strong><a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a></strong>
            <span class="status-badge status-<?php echo esc_attr($post->post_status); ?>"><?php echo esc_html($status_config['label']); ?></span>
        </div>
        <div class="<?php echo esc_attr($post_type); ?>-meta">
            <span class="post-date"><?php echo esc_html(mysql2date('j.n.Y H:i', $post->post_date)); ?></span>
            <?php if ($assigned_points): ?>
                <span class="points-assigned">Přiděleno: <?php echo esc_html($assigned_points); ?> bodů</span>
            <?php endif; ?>
            <?php if ($awarded_points > 0): ?>
                <span class="points-awarded">Uděleno: <?php echo esc_html((string)$awarded_points); ?> bodů</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($post->post_content): ?>
        <div class="<?php echo esc_attr($post_type); ?>-excerpt">
            <?php echo esc_html(wp_trim_words($post->post_content, 20, '...')); ?>
        </div>
    <?php endif; ?>

    <!-- Domain-specific details section -->
    <?php $renderer->renderDomainDetails($field_data); ?>

    <!-- Gallery section -->
    <?php if (!empty($gallery_images) && is_array($gallery_images)): ?>
        <?php $renderer->load_template('post-gallery.php', compact('gallery_images', 'post_type')); ?>
    <?php endif; ?>

    <!-- Rejection reason section -->
    <?php if ($post->post_status === 'rejected' && $rejection_reason): ?>
        <div class="rejection-reason">
            <strong>Důvod odmítnutí:</strong> <?php echo esc_html($rejection_reason); ?>
        </div>
    <?php endif; ?>

    <!-- Action buttons section -->
    <?php $renderer->load_template('post-actions.php', compact('post', 'post_type', 'assigned_points', 'rejection_reason')); ?>
</div>