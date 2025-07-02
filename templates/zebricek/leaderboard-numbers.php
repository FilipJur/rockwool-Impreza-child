<?php
/**
 * Žebříček Ranking Numbers Template
 * 
 * Display ranking numbers 1-5 horizontally
 * 
 * @var array $attributes Shortcode attributes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zebricek-ranking-numbers flex items-center justify-center space-x-6 mb-6">
    <?php for ($i = 1; $i <= 5; $i++): ?>
        <span class="zebricek-rank-number <?= $i === 1 ? 'zebricek-rank-number--first font-bold' : 'font-normal' ?> text-lg">
            <?= esc_html((string)$i) ?>
        </span>
    <?php endfor; ?>
</div>