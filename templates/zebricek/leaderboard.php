<?php
/**
 * Žebříček Leaderboard Template
 *
 * Main container template for the leaderboard display
 *
 * @var array $leaderboard_data Array of user data for leaderboard
 * @var array $attributes Shortcode attributes
 * @var bool $is_full_member Whether current user is full member
 * @var string $wrapper_classes CSS classes for wrapper
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?= esc_attr($wrapper_classes) ?> space-y-6">

    <?php if ($attributes['show_numbers'] === 'true'): ?>
        <?php echo $renderer->load_template('zebricek/leaderboard-numbers.php', compact('attributes')); ?>
    <?php endif; ?>

    <div class="zebricek-leaderboard space-y-4">

        <?php echo $renderer->load_template('zebricek/leaderboard-header.php', compact('is_full_member')); ?>

        <div class="zebricek-list grid gap-10">
            <?php if (!empty($leaderboard_data) && is_array($leaderboard_data)): ?>
                <?php foreach ($leaderboard_data as $index => $user_data): ?>
                    <?php
                    $position = (int)$attributes['offset'] + $index + 1;
                    $template_data = compact('user_data', 'position', 'is_full_member');
                    echo $renderer->load_template('zebricek/leaderboard-row.php', $template_data);
                    ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="zebricek-empty p-4 text-center">
                    <p class="text-sm">Žebříček zatím neobsahuje žádné uživatele.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($attributes['show_pagination'] === 'true' && count($leaderboard_data) >= (int)$attributes['limit']): ?>
            <?php
            $pagination_data = [
                'offset' => (int)$attributes['offset'] + (int)$attributes['limit'],
                'limit' => $attributes['limit'],
                'has_more' => count($leaderboard_data) >= (int)$attributes['limit']
            ];
            echo $renderer->load_template('zebricek/leaderboard-pagination.php', $pagination_data);
            ?>
        <?php endif; ?>

    </div>
</div>
