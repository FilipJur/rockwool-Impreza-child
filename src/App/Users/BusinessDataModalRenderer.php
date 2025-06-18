<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Business Data Modal Renderer - HTML Generation for Business Data Display
 *
 * Handles generation of HTML content for business data review modal.
 * Separated from BusinessDataModal for single responsibility principle.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BusinessDataModalRenderer {

    /**
     * Generate HTML for business data display
     *
     * @param array $business_data Business data array
     * @return string HTML content
     */
    public function generate_business_data_html(array $business_data): string {
        ob_start();
        ?>
        <div class="mistr-business-data-review">
            <!-- Company Information Section -->
            <section class="mistr-company-info">
                <h3><?php esc_html_e('Informace o společnosti', 'mistr-fachman'); ?></h3>
                <div class="mistr-data-grid">
                    <div class="mistr-data-row">
                        <label><?php esc_html_e('IČO:', 'mistr-fachman'); ?></label>
                        <span class="mistr-ico-value"><?php echo esc_html($business_data['ico']); ?></span>
                        <?php if ($business_data['validation']['ares_verified']): ?>
                            <span class="mistr-ares-status verified">✓ ARES Ověřeno</span>
                        <?php else: ?>
                            <span class="mistr-ares-status unverified">✗ ARES Neověřeno</span>
                        <?php endif; ?>
                    </div>
                    <div class="mistr-data-row">
                        <label><?php esc_html_e('Název společnosti:', 'mistr-fachman'); ?></label>
                        <span><?php echo esc_html($business_data['company_name']); ?></span>
                    </div>
                    <div class="mistr-data-row">
                        <label><?php esc_html_e('Sídlo:', 'mistr-fachman'); ?></label>
                        <span><?php echo esc_html($business_data['address']); ?></span>
                    </div>
                    
                    <?php if (isset($business_data['validation']['ares_data']['obchodniJmeno'])): ?>
                    <div class="mistr-data-row">
                        <label><?php esc_html_e('ARES název:', 'mistr-fachman'); ?></label>
                        <span><?php echo esc_html($business_data['validation']['ares_data']['obchodniJmeno']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Representative Information Section -->
            <section class="mistr-representative-info">
                <h3><?php esc_html_e('Kontaktní osoba', 'mistr-fachman'); ?></h3>
                <div class="mistr-data-grid">
                    <div class="mistr-data-row">
                        <label><?php esc_html_e('Jméno:', 'mistr-fachman'); ?></label>
                        <span><?php echo esc_html($business_data['representative']['first_name'] . ' ' . $business_data['representative']['last_name']); ?></span>
                    </div>
                    <div class="mistr-data-row">
                        <label><?php esc_html_e('Pozice:', 'mistr-fachman'); ?></label>
                        <span><?php echo esc_html($business_data['representative']['position']); ?></span>
                    </div>
                    <div class="mistr-data-row">
                        <label><?php esc_html_e('E-mail:', 'mistr-fachman'); ?></label>
                        <span><a href="mailto:<?php echo esc_attr($business_data['representative']['email']); ?>">
                            <?php echo esc_html($business_data['representative']['email']); ?>
                        </a></span>
                    </div>
                </div>
            </section>

            <!-- Business Criteria Section -->
            <section class="mistr-business-criteria">
                <h3><?php esc_html_e('Obchodní kritéria', 'mistr-fachman'); ?></h3>
                <div class="mistr-criteria-list">
                    <div class="mistr-criteria-item">
                        <span class="mistr-status <?php echo $business_data['acceptances']['zateplovani'] ? 'accepted' : 'rejected'; ?>">
                            <?php echo $business_data['acceptances']['zateplovani'] ? '✓' : '✗'; ?>
                        </span>
                        <span><?php esc_html_e('Věnuje se zateplování rodinných domů do 800 m² plochy', 'mistr-fachman'); ?></span>
                    </div>
                    <div class="mistr-criteria-item">
                        <span class="mistr-status <?php echo $business_data['acceptances']['kriteria_obratu'] ? 'accepted' : 'rejected'; ?>">
                            <?php echo $business_data['acceptances']['kriteria_obratu'] ? '✓' : '✗'; ?>
                        </span>
                        <span><?php esc_html_e('Splňuje kritéria obratu do 50 mil. Kč', 'mistr-fachman'); ?></span>
                    </div>
                </div>
            </section>

            <?php if (!empty($business_data['promo_code'])): ?>
            <!-- Promo Code Section -->
            <section class="mistr-promo-code">
                <h3><?php esc_html_e('Promo kód', 'mistr-fachman'); ?></h3>
                <div class="mistr-data-grid">
                    <div class="mistr-data-row">
                        <label><?php esc_html_e('Kód:', 'mistr-fachman'); ?></label>
                        <span class="mistr-promo-code-value"><?php echo esc_html($business_data['promo_code']); ?></span>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Validation Info Section -->
            <section class="mistr-validation-info">
                <h3><?php esc_html_e('Informace o validaci', 'mistr-fachman'); ?></h3>
                <div class="mistr-data-grid">
                    <div class="mistr-data-row">
                        <label><?php esc_html_e('Datum odeslání:', 'mistr-fachman'); ?></label>
                        <span><?php echo esc_html(mysql2date('j.n.Y H:i', $business_data['submitted_at'])); ?></span>
                    </div>
                    <div class="mistr-data-row">
                        <label><?php esc_html_e('ARES validace:', 'mistr-fachman'); ?></label>
                        <span><?php echo esc_html(mysql2date('j.n.Y H:i', $business_data['validation']['verified_at'])); ?></span>
                    </div>
                </div>
            </section>
        </div>

        <?php
        return ob_get_clean();
    }
}