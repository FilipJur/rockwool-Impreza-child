<?php
/**
 * Post Actions Template
 * 
 * Action buttons based on post status
 * 
 * @var \WP_Post $post Post object
 * @var string $post_type Post type slug
 * @var int $assigned_points Assigned points
 * @var string $rejection_reason Current rejection reason
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<?php if ($post->post_status === 'pending'): ?>
    <div class="<?php echo esc_attr($post_type); ?>-actions">
        <div class="points-input">
            <label>Body: </label>
            <input type="number"
                   class="quick-points-input small-text"
                   placeholder="0"
                   data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                   value="<?php echo esc_attr($assigned_points ?: ''); ?>"
                   style="width: 60px;">
            <button type="button"
                    class="button button-primary action-approve"
                    data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                    data-action="approve">Schválit</button>
        </div>
        <div class="rejection-input">
            <textarea class="rejection-reason-input"
                      data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                      placeholder="Důvod zamítnutí..."
                      rows="2" style="width: 100%; margin-bottom: 5px;"></textarea>
            <button type="button"
                    class="button action-reject"
                    data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                    data-action="reject">Odmítnout</button>
        </div>
    </div>

<?php elseif ($post->post_status === 'publish'): ?>
    <div class="<?php echo esc_attr($post_type); ?>-actions">
        <button type="button"
                class="button action-reject"
                data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                data-action="reject">Zrušit schválení</button>
    </div>

<?php elseif ($post->post_status === 'rejected'): ?>
    <div class="<?php echo esc_attr($post_type); ?>-actions">
        <label>Body: </label>
        <input type="number"
               class="quick-points-input small-text"
               placeholder="0"
               data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
               value="<?php echo esc_attr($assigned_points ?: ''); ?>"
               style="width: 60px;">
        <button type="button"
                class="button button-primary action-approve"
                data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                data-action="approve">Schválit</button>

        <div style="margin-top: 10px;">
            <textarea class="rejection-reason-input"
                      data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                      placeholder="Upravit důvod odmítnutí..."
                      rows="2" style="width: 100%; margin-bottom: 5px;"><?php echo esc_textarea($rejection_reason); ?></textarea>
            <button type="button"
                    class="button save-rejection-btn"
                    data-post-id="<?php echo esc_attr((string)$post->ID); ?>">Uložit důvod</button>
        </div>
    </div>
<?php endif; ?>