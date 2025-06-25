<?php
/**
 * Status Section Template
 * 
 * Reusable section for displaying posts by status
 * 
 * @var string $status Status slug (pending, rejected, approved)
 * @var string $title Section title
 * @var array $posts Array of WP_Post objects
 * @var string $post_type Post type slug
 * @var bool $collapsible Whether section can be collapsed
 * @var bool $collapsed Whether section starts collapsed
 * @var bool $compact Whether to use compact card rendering
 * @var object $renderer The renderer instance for helper methods
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$status_map = [
    'pending' => 'pending',
    'rejected' => 'rejected', 
    'approved' => 'approved'
];

$section_status = $status_map[$status] ?? $status;
?>

<div class="<?php echo esc_attr($post_type); ?>-section section-<?php echo esc_attr($section_status); ?>" data-status="<?php echo esc_attr($status); ?>">
    <div class="section-header">
        <h5 class="section-title">
            <span class="status-indicator <?php echo esc_attr($section_status); ?>">●</span>
            <?php echo esc_html($title); ?> (<?php echo esc_html((string)count($posts)); ?>)
        </h5>
        <?php if ($collapsible): ?>
            <button type="button" class="section-toggle" data-target="section-<?php echo esc_attr($section_status); ?>">
                <span class="toggle-icon"><?php echo $collapsed ? '▶' : '▼'; ?></span>
            </button>
        <?php endif; ?>
    </div>
    <div class="section-content<?php echo $collapsible ? ' collapsible' : ''; ?><?php echo $collapsed ? ' collapsed' : ''; ?>" data-section="<?php echo esc_attr($section_status); ?>">
        <div class="<?php echo esc_attr($post_type); ?>-list<?php echo $compact ? ' ' . esc_attr($post_type) . '-list-compact' : ''; ?>">
            <?php foreach ($posts as $post): ?>
                <?php 
                if ($compact) {
                    $renderer->load_template('post-card-compact.php', compact('post', 'post_type'));
                } else {
                    $renderer->load_template('post-card-full.php', compact('post', 'post_type', 'renderer'));
                }
                ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>