<?php
/**
 * Žebříček Progress Template
 * 
 * Progress display showing position and progress to next user with pending points
 * Matches Figma design with three-layer progress bar
 * 
 * @var array $progress_data Progress information
 * @var int $progress_data['current_position'] User's current position
 * @var int $progress_data['current_points'] User's current points  
 * @var int $progress_data['pending_points'] User's pending points
 * @var array|null $progress_data['next_target'] Next target user info
 * @var string $progress_data['year'] Current year
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zebricek-progress">
    <?php if ($progress_data['next_target']): ?>
        
        <!-- Main progress section -->
        <div class="zebricek-progress__content">
            <!-- Progress bar with three layers like UserPointsBalance -->
            <div class="zebricek-progress__bar-container">
                <!-- Base layer: remaining capacity (gray background) -->
                <div class="zebricek-progress__bar-background"></div>
                
                <!-- Middle layer: pending points (pink) -->
                <?php if ($progress_data['next_target']['has_pending']): ?>
                    <div class="zebricek-progress__bar-pending" style="width: <?= $progress_data['next_target']['pending_end_percentage'] ?>%"></div>
                <?php endif; ?>
                
                <!-- Top layer: accepted points (red) -->
                <div class="zebricek-progress__bar-accepted" style="width: <?= $progress_data['next_target']['accepted_percentage'] ?>%"></div>
            </div>
            
            <!-- Position text -->
            <div class="zebricek-progress__position">
                <?= esc_html($progress_data['current_position']) ?>. místo
            </div>
        </div>
        
        <!-- Details section - horizontal layout with space-between -->
        <div class="zebricek-progress__details">
            <div class="zebricek-progress__points-needed">
                Zbývá <?= number_format($progress_data['next_target']['points_needed']) ?> b. do překonání uživatele
            </div>
            <div class="zebricek-progress__target-user">
                <?= esc_html($progress_data['next_target']['display_name']) ?> (<?= number_format($progress_data['next_target']['points']) ?> b.)
            </div>
        </div>
        
    <?php else: ?>
        <!-- Top position state -->
        <div class="zebricek-progress__success">
            <div class="zebricek-progress__position zebricek-progress__position--success">
                1. místo
            </div>
            <div class="zebricek-progress__success-message">
                Jste na vrcholu žebříčku!
            </div>
        </div>
    <?php endif; ?>
</div>