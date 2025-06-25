<?php
/**
 * Post Gallery Template
 * 
 * Gallery images section for posts
 * 
 * @var array $gallery_images Array of image data
 * @var string $post_type Post type slug
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?php echo esc_attr($post_type); ?>-gallery">
    <?php foreach (array_slice($gallery_images, 0, 3) as $image): ?>
        <?php
        $image_url = '';
        $image_alt = '';

        if (is_array($image) && isset($image['sizes']['thumbnail'])) {
            // ACF image array format
            $image_url = $image['sizes']['thumbnail'];
            $image_alt = $image['alt'] ?? '';
        } elseif (is_numeric($image)) {
            // Image ID format
            $image_url = wp_get_attachment_image_url($image, 'thumbnail');
            $image_alt = get_post_meta($image, '_wp_attachment_image_alt', true);
        }

        if ($image_url):
        ?>
            <img src="<?php echo esc_url($image_url); ?>"
                 alt="<?php echo esc_attr($image_alt); ?>"
                 class="gallery-thumb">
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (count($gallery_images) > 3): ?>
        <span class="gallery-more">+<?php echo count($gallery_images) - 3; ?> dalších</span>
    <?php endif; ?>
</div>