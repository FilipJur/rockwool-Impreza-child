<?php
/**
 * Dashboard Wrapper Template
 * 
 * Main container for admin dashboard cards
 * 
 * @var array $stats User statistics data
 * @var array $posts_by_status Posts grouped by status  
 * @var string $domain_name Display name for domain
 * @var string $post_type Post type slug
 * @var object $renderer The renderer instance for helper methods
 * @var \WP_User $user The user object
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="management-card <?php echo esc_attr($post_type); ?>-dashboard">
    <div class="card-header">
        <h4 class="card-title">Správa <?php echo esc_html($domain_name); ?></h4>

        <?php 
        // Include stats header template
        $renderer->load_template('stats-header.php', compact('stats', 'post_type', 'user')); 
        ?>
    </div>

    <div class="card-content">
        <?php if ($stats['total'] === 0): ?>
            <div class="empty-state">
                <p class="empty-text">Žádné <?php echo esc_html(strtolower($domain_name)); ?></p>
            </div>
        <?php else: ?>

            <!-- PENDING SECTION (High Priority) -->
            <?php if (!empty($posts_by_status['pending'])): ?>
                <?php 
                $renderer->load_template('status-section.php', [
                    'status' => 'pending',
                    'title' => 'Čeká na schválení',
                    'posts' => $posts_by_status['pending'],
                    'post_type' => $post_type,
                    'collapsible' => false,
                    'collapsed' => false,
                    'compact' => false,
                    'renderer' => $renderer
                ]); 
                ?>
            <?php endif; ?>

            <!-- REJECTED SECTION (Medium Priority) -->
            <?php if (!empty($posts_by_status['rejected'])): ?>
                <?php 
                $renderer->load_template('status-section.php', [
                    'status' => 'rejected',
                    'title' => 'Odmítnuto',
                    'posts' => $posts_by_status['rejected'],
                    'post_type' => $post_type,
                    'collapsible' => true,
                    'collapsed' => false,
                    'compact' => false,
                    'renderer' => $renderer
                ]); 
                ?>
            <?php endif; ?>

            <!-- COMPLETED SECTION (Low Priority - Always shown but collapsed) -->
            <?php if (!empty($posts_by_status['approved'])): ?>
                <?php 
                $renderer->load_template('status-section.php', [
                    'status' => 'approved',
                    'title' => 'Schváleno',
                    'posts' => $posts_by_status['approved'],
                    'post_type' => $post_type,
                    'collapsible' => true,
                    'collapsed' => true,
                    'compact' => true,
                    'renderer' => $renderer
                ]); 
                ?>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>