<?php
/**
 * Žebříček Leaderboard Pagination Template
 * 
 * Pagination section with "Více" button
 * 
 * @var int $offset Next offset value
 * @var string $limit Items per page
 * @var bool $has_more Whether there are more items
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<?php if ($has_more): ?>
    <div class="zebricek-pagination mt-6 text-center">
        <button class="zebricek-more-btn px-6 py-2 font-medium" 
                data-offset="<?= esc_attr((string)$offset) ?>"
                data-limit="<?= esc_attr($limit) ?>">
            <?= esc_html__('Více', 'mistr-fachman') ?>
        </button>
    </div>
<?php endif; ?>