<?php
/**
 * Realizace Details Template
 * 
 * Domain-specific field rendering for realizace
 * 
 * @var array $field_data Domain-specific field data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$area = $field_data['area'] ?? '';
$construction_type = $field_data['construction_type'] ?? '';
$materials = $field_data['materials'] ?? '';
?>

<?php if ($area || $construction_type || $materials): ?>
    <div class="realization-details">
        <?php if ($area): ?>
            <div class="detail-item">
                <span class="detail-label">Plocha:</span>
                <span class="detail-value"><?php echo esc_html((string)$area); ?> m²</span>
            </div>
        <?php endif; ?>
        <?php if ($construction_type): ?>
            <div class="detail-item">
                <span class="detail-label">Konstrukce:</span>
                <span class="detail-value"><?php echo esc_html(wp_trim_words($construction_type, 8, '...')); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($materials): ?>
            <div class="detail-item">
                <span class="detail-label">Materiály:</span>
                <span class="detail-value"><?php echo esc_html(wp_trim_words($materials, 8, '...')); ?></span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>