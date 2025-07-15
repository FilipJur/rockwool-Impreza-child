<?php
/**
 * Žebříček Position Stats Template
 * 
 * Annual points and position statistics matching exact Figma design
 * 
 * @var string $annual_points Formatted annual points
 * @var string $year Current year
 * @var string $position Formatted position (number only)
 * @var int $realizations_count Number of realizations added
 * @var int $invoices_count Number of invoices added
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zebricek-position-stats">
    <div class="zebricek-stats-container">
        
        <!-- Position stat -->
        <div class="zebricek-stat zebricek-stat--position">
            <div class="zebricek-stat__value zebricek-stat__value--primary">
                <?= esc_html($position) ?>
            </div>
            <div class="zebricek-stat__label zebricek-stat__label--center">
                <?= esc_html__('Aktuální pozice', 'mistr-fachman') ?>
            </div>
        </div>

        <!-- Divider -->
        <div class="zebricek-divider"></div>

        <!-- Annual points stat -->
        <div class="zebricek-stat zebricek-stat--points">
            <div class="zebricek-stat__value">
                <?= esc_html($annual_points) ?>
            </div>
            <div class="zebricek-stat__label zebricek-stat__label--center">
                <?= esc_html(sprintf(__('Celkem bodů za rok %s', 'mistr-fachman'), $year)) ?>
            </div>
        </div>

        <!-- Divider -->
        <div class="zebricek-divider"></div>

        <!-- Realizations stat -->
        <div class="zebricek-stat zebricek-stat--realizations">
            <div class="zebricek-stat__value">
                <?= esc_html($realizations_count ?? 0) ?>
            </div>
            <div class="zebricek-stat__label zebricek-stat__label--center">
                <?= esc_html__('Přidaných realizací', 'mistr-fachman') ?>
            </div>
        </div>

    </div>
</div>