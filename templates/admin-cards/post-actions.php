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
            <?php if ($post_type === 'faktura'): ?>
                <!-- Faktury: Show calculated points (read-only) -->
                <?php 
                // Calculate points from invoice value: floor(value / 30)
                $invoice_value = function_exists('get_field') ? get_field('hodnota_faktury', $post->ID) : 0;
                $calculated_points = $invoice_value > 0 ? (int) floor($invoice_value / 30) : 0;
                ?>
                <label>Body (vypočítané): </label>
                <span class="calculated-points"><?php echo esc_html((string)$calculated_points); ?></span>
                <input type="hidden" class="quick-points-input" data-post-id="<?php echo esc_attr((string)$post->ID); ?>" value="<?php echo esc_attr((string)$calculated_points); ?>">
            <?php else: ?>
                <!-- Realizace: Show editable points input -->
                <label>Body: </label>
                <input type="number"
                       class="quick-points-input small-text"
                       placeholder="0"
                       data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                       value="<?php echo esc_attr($assigned_points ?: ''); ?>">
            <?php endif; ?>
            <button type="button"
                    class="button button-primary action-approve"
                    data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                    data-action="approve">Schválit</button>
        </div>
        <div class="rejection-input">
            <textarea class="rejection-reason-input"
                      data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                      placeholder="Důvod zamítnutí..."
                      rows="2"></textarea>
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
        <?php if ($post_type === 'faktura'): ?>
            <!-- Faktury: Show calculated points (read-only) -->
            <?php 
            // Calculate points from invoice value: floor(value / 30)
            $invoice_value = function_exists('get_field') ? get_field('hodnota_faktury', $post->ID) : 0;
            $calculated_points = $invoice_value > 0 ? (int) floor($invoice_value / 30) : 0;
            ?>
            <label>Body (vypočítané): </label>
            <span class="calculated-points"><?php echo esc_html((string)$calculated_points); ?></span>
            <input type="hidden" class="quick-points-input" data-post-id="<?php echo esc_attr((string)$post->ID); ?>" value="<?php echo esc_attr((string)$calculated_points); ?>">
        <?php else: ?>
            <!-- Realizace: Show editable points input -->
            <label>Body: </label>
            <input type="number"
                   class="quick-points-input small-text"
                   placeholder="0"
                   data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                   value="<?php echo esc_attr($assigned_points ?: ''); ?>">
        <?php endif; ?>
        <button type="button"
                class="button button-primary action-approve"
                data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                data-action="approve">Schválit</button>

        <div class="rejection-input-wrapper">
            <textarea class="rejection-reason-input"
                      data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                      placeholder="Upravit důvod odmítnutí..."
                      rows="2"><?php echo esc_textarea($rejection_reason); ?></textarea>
            <button type="button"
                    class="button save-rejection-btn"
                    data-post-id="<?php echo esc_attr((string)$post->ID); ?>">Uložit důvod</button>
        </div>
    </div>
<?php endif; ?>