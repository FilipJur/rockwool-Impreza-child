<?php
/**
 * Žebříček Position Stats Template
 * 
 * Annual points and position statistics
 * 
 * @var string $annual_points Formatted annual points
 * @var string $year Current year
 * @var string $position Formatted position
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zebricek-annual-points mb-6">
    <div class="zebricek-stat p-4 space-y-2">
        <span class="zebricek-stat-label block text-sm font-medium uppercase tracking-wide">
            <?= esc_html(sprintf(__('získané body za %s', 'mistr-fachman'), $year)) ?>
        </span>
        <span class="zebricek-stat-value block text-lg font-bold">
            <?= esc_html($annual_points) ?>
        </span>
    </div>
    <p class="zebricek-stat-description text-sm mt-2">
        <?= esc_html__('Body získané v daném roce určující pořadí v soutěži.', 'mistr-fachman') ?>
    </p>
</div>

<div class="zebricek-user-position">
    <div class="zebricek-stat p-4 space-y-2">
        <span class="zebricek-stat-label block text-sm font-medium uppercase tracking-wide">
            <?= esc_html__('Vaše umístění v žebříčku', 'mistr-fachman') ?>
        </span>
        <span class="zebricek-stat-value block text-lg font-bold">
            <?= esc_html($position) ?>
        </span>
    </div>
    <p class="zebricek-stat-description text-sm mt-2">
        <?= esc_html(sprintf(__('Období %s končí 31. 12. %s.', 'mistr-fachman'), $year, $year)) ?>
    </p>
</div>