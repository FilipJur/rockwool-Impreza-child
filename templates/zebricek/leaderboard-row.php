<?php
/**
 * Žebříček Leaderboard Row Template
 *
 * Individual user row in the leaderboard
 *
 * @var array $user_data User data (user_id, display_name, first_name, last_name, company, points, formatted_points)
 * @var int $position User's position in leaderboard
 * @var bool $is_full_member Whether current user is full member
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Safely get user name
$user_name = '';
if (!empty($user_data['first_name']) || !empty($user_data['last_name'])) {
    $user_name = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));
}
if (empty($user_name)) {
    $user_name = $user_data['display_name'] ?? 'Neznámý uživatel';
}

// Determine row styling based on position
$row_classes = 'zebricek-row flex justify-between items-center bg-gray-100';
if ($position <= 10) {
    $row_classes .= ' zebricek-row--top-ten';
}
if ($position > 20) {
    $row_classes .= ' zebricek-row--bottom';
}
?>

<div class="<?= esc_attr($row_classes) ?>">
    <div class="zebricek-user-info flex-1">
        <h4 class="zebricek-user-name font-medium">
            <?= esc_html($user_name) ?>
        </h4>

        <?php if ($is_full_member && !empty($user_data['company'])): ?>
            <p class="zebricek-company text-sm mt-1">
                <?= esc_html($user_data['company']) ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($is_full_member): ?>
        <div class="zebricek-actions flex items-center">
            <span class="zebricek-points font-medium">
                <?= esc_html($user_data['formatted_points'] ?? '0 b.') ?>
            </span>
        </div>
    <?php endif; ?>
</div>
