<?php
/**
 * Žebříček Leaderboard Header Template
 * 
 * Header section with title and optional points column
 * 
 * @var bool $is_full_member Whether current user is full member
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zebricek-header flex justify-between items-center mb-4">
    <h3 class="zebricek-title text-lg font-medium">
        <?= esc_html__('Jak si zatím stojí ostatní?', 'mistr-fachman') ?>
    </h3>
    <?php if ($is_full_member): ?>
        <span class="zebricek-points-header text-sm font-medium">
            <?= esc_html__('Počet bodů', 'mistr-fachman') ?>
        </span>
    <?php endif; ?>
</div>