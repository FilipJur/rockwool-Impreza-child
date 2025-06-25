<?php
/**
 * Faktura Details Template
 * 
 * Domain-specific field rendering for faktury
 * 
 * @var array $field_data Domain-specific field data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$value = $field_data['value'] ?? 0;
$invoice_number = $field_data['invoice_number'] ?? '';
$invoice_date = $field_data['invoice_date'] ?? '';
$file = $field_data['file'] ?? null;

// Format the invoice value with thousands separator
$formatted_value = number_format($value, 0, ',', ' ');

// Format the invoice date if available
$formatted_date = '';
if ($invoice_date) {
    $date_object = DateTime::createFromFormat('Ymd', $invoice_date);
    if ($date_object) {
        $formatted_date = $date_object->format('d.m.Y');
    }
}
?>

<div class="faktura-details">
    <?php if ($value > 0): ?>
        <div class="detail-item detail-value-highlight">
            <span class="detail-label">Hodnota:</span>
            <span class="detail-value"><?php echo esc_html($formatted_value); ?> Kč</span>
        </div>
    <?php endif; ?>
    
    <?php if ($invoice_number): ?>
        <div class="detail-item">
            <span class="detail-label">Číslo faktury:</span>
            <span class="detail-value"><?php echo esc_html($invoice_number); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($formatted_date): ?>
        <div class="detail-item">
            <span class="detail-label">Datum:</span>
            <span class="detail-value"><?php echo esc_html($formatted_date); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($file && is_array($file)): ?>
        <div class="detail-item">
            <span class="detail-label">Soubor:</span>
            <span class="detail-value">
                <?php if (!empty($file['filename'])): ?>
                    <a href="<?php echo esc_url($file['url'] ?? '#'); ?>" target="_blank" class="file-link">
                        <?php echo esc_html(wp_trim_words($file['filename'], 3, '...')); ?>
                    </a>
                <?php else: ?>
                    <em>Přiložen</em>
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>
</div>