<?php
/**
 * Žebříček Position Template
 * 
 * User position widget template
 * 
 * @var array $position_data Position and points data
 * @var array $attributes Shortcode attributes
 * @var string $wrapper_classes CSS classes for wrapper
 * @var string $user_status User registration status
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?= esc_attr($wrapper_classes) ?>">
    
    <?php if ($user_status === 'logged_out'): ?>
        <div class="zebricek-position zebricek-position--logged-out p-4 text-center">
            <p class="text-sm">
                <?= esc_html__('Pro zobrazení pozice v žebříčku se prosím přihlaste.', 'mistr-fachman') ?>
            </p>
        </div>
        
    <?php elseif ($user_status === 'pending'): ?>
        <div class="zebricek-position zebricek-position--pending p-4 text-center">
            <p class="text-sm">
                <?= esc_html__('Pozice v žebříčku bude dostupná po schválení registrace.', 'mistr-fachman') ?>
            </p>
        </div>
        
    <?php else: ?>
        <div class="zebricek-position-content space-y-6">
            
            <?php if ($attributes['show_annual_points'] === 'true' && !empty($position_data)): ?>
                <?php 
                $stats_data = [
                    'annual_points' => $position_data['annual_points_formatted'] ?? '0 b.',
                    'year' => $position_data['year'] ?? date('Y'),
                    'position' => $position_data['position_formatted'] ?? '0. pozice'
                ];
                echo $renderer->load_template('zebricek/position-stats.php', $stats_data);
                ?>
            <?php endif; ?>
            
        </div>
    <?php endif; ?>
    
</div>