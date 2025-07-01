<?php
/**
 * Stats Header Template
 *
 * Statistics grid and action buttons
 *
 * @var array $stats User statistics data
 * @var string $post_type Post type slug
 * @var \WP_User $user The user object
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Integrated Stats Header -->
<div class="stats-header">
    <div class="stats-grid">
        <div class="stat-item">
            <span class="stat-label">Celkem:</span>
            <span class="stat-value"><?php echo esc_html((string)$stats['total']); ?></span>
        </div>
        <?php if ($stats['pending'] > 0): ?>
            <div class="stat-item stat-pending">
                <span class="stat-label">Čeká:</span>
                <span class="stat-value"><?php echo esc_html((string)$stats['pending']); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($stats['approved'] > 0): ?>
            <div class="stat-item stat-approved">
                <span class="stat-label">Schváleno:</span>
                <span class="stat-value"><?php echo esc_html((string)$stats['approved']); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($stats['rejected'] > 0): ?>
            <div class="stat-item stat-rejected">
                <span class="stat-label">Odmítnuto:</span>
                <span class="stat-value"><?php echo esc_html((string)$stats['rejected']); ?></span>
            </div>
        <?php endif; ?>
        <div class="stat-item stat-points">
            <span class="stat-label">Body celkem:</span>
            <span class="stat-value"><?php echo esc_html((string)$stats['total_points']); ?></span>
        </div>
    </div>

    <?php if ($stats['total'] > 0): ?>
        <div class="stats-actions">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $post_type . '&author=' . $user->ID)); ?>"
               class="button button-secondary button-small">Zobrazit všechny</a>

            <?php if ($post_type === 'realization' && $stats['pending'] > 0): ?>
                <button type="button"
                        class="button button-primary button-small bulk-approve-btn"
                        data-user-id="<?php echo esc_attr((string)$user->ID); ?>"
                        data-post-type="<?php echo esc_attr($post_type); ?>">
                    Hromadně schválit
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
