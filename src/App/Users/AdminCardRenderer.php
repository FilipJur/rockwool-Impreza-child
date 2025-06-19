<?php

declare(strict_types=1);

namespace MistrFachman\Users;

/**
 * Admin Card Renderer - UI Component Rendering
 *
 * Handles all card-based UI component rendering for the admin interface.
 * Follows React-like component patterns with data injection.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminCardRenderer {

    public function __construct(
        private RoleManager $role_manager,
        private BusinessDataManager $business_manager
    ) {}

    /**
     * Render user status card
     */
    public function render_user_status_card(\WP_User $user, bool $is_pending): void {
        $current_status = $this->role_manager->get_user_status($user->ID);
        $status_display = $is_pending ? RegistrationStatus::getDisplayName($current_status) : 'Schválený člen';
        $status_color = $is_pending ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
        ?>
        <div class="management-card">
            <div class="card-header">
                <h4 class="card-title">
                    <span class="card-icon">👤</span>
                    Stav účtu
                </h4>
            </div>
            <div class="card-content">
                <div class="status-display">
                    <span class="status-badge <?php echo esc_attr($status_color); ?>">
                        <?php echo esc_html($status_display); ?>
                    </span>
                </div>
                <p class="status-description">
                    <?php echo esc_html($this->getStatusDescription($current_status, $is_pending)); ?>
                </p>
                <?php if ($is_pending): ?>
                    <div class="status-timeline">
                        <div class="timeline-item <?php echo $current_status === RegistrationStatus::NEEDS_FORM ? 'active' : 'completed'; ?>">
                            <span class="timeline-marker"></span>
                            <span class="timeline-text">Registrace dokončena</span>
                        </div>
                        <div class="timeline-item <?php echo $current_status === RegistrationStatus::AWAITING_REVIEW ? 'active' : ($current_status === RegistrationStatus::APPROVED ? 'completed' : ''); ?>">
                            <span class="timeline-marker"></span>
                            <span class="timeline-text">Formulář odeslán</span>
                        </div>
                        <div class="timeline-item">
                            <span class="timeline-marker"></span>
                            <span class="timeline-text">Schválení admin</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render business data card
     */
    public function render_business_data_card(\WP_User $user): void {
        $has_business_data = $this->business_manager->has_user_data($user->ID, BusinessDataManager::DATA_TYPE_BUSINESS);
        ?>
        <div class="management-card">
            <div class="card-header">
                <h4 class="card-title">
                    <span class="card-icon">🏢</span>
                    Firemní údaje
                </h4>
            </div>
            <div class="card-content">
                <?php if ($has_business_data): ?>
                    <?php $business_data = $this->business_manager->get_user_data($user->ID, BusinessDataManager::DATA_TYPE_BUSINESS); ?>
                    <div class="business-summary">
                        <div class="summary-item">
                            <span class="summary-label">Společnost:</span>
                            <span class="summary-value"><?php echo esc_html($business_data['company_name']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">IČO:</span>
                            <span class="summary-value"><?php echo esc_html($business_data['ico']); ?></span>
                            <?php if ($business_data['validation']['ares_verified']): ?>
                                <span class="verification-badge bg-green-100 text-green-800">✓ ARES</span>
                            <?php endif; ?>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Kontakt:</span>
                            <span class="summary-value"><?php echo esc_html($business_data['representative']['first_name'] . ' ' . $business_data['representative']['last_name']); ?></span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button type="button" 
                                class="w-btn us-btn-style_1 review-business-data-btn" 
                                data-user-id="<?php echo esc_attr((string)$user->ID); ?>">
                            <span class="button-icon">📋</span>
                            Zobrazit detaily
                        </button>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">📝</span>
                        <p class="empty-text">Firemní údaje nejsou k dispozici</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render admin actions card
     */
    public function render_admin_actions_card(\WP_User $user, bool $is_pending): void {
        $current_status = $this->role_manager->get_user_status($user->ID);
        ?>
        <div class="management-card">
            <div class="card-header">
                <h4 class="card-title">
                    <span class="card-icon">⚡</span>
                    Administrace
                </h4>
            </div>
            <div class="card-content">
                <?php if ($is_pending): ?>
                    <?php if ($current_status === RegistrationStatus::AWAITING_REVIEW): ?>
                        <div class="action-description">
                            <p>Uživatel vyplnil registrační formulář a čeká na schválení.</p>
                        </div>
                        <div class="card-actions">
                            <?php $this->render_promote_user_button_enhanced($user->ID); ?>
                        </div>
                    <?php else: ?>
                        <div class="action-description">
                            <p>Uživatel ještě nevyplnil závěrečný formulář.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="action-description">
                        <p>Uživatel je schválený člen s plným přístupem.</p>
                    </div>
                    <div class="card-actions">
                        <?php $this->render_revoke_user_button_enhanced($user->ID); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render projekty & realizace card (future-ready)
     */
    public function render_future_features_card(\WP_User $user): void {
        // Check for future realizace data
        $has_realizace_data = $this->business_manager->has_user_data($user->ID, BusinessDataManager::DATA_TYPE_REALIZACE);
        $realizace_count = $this->get_user_realizace_count($user->ID);
        $pending_realizace = $this->get_pending_realizace_count($user->ID);
        
        ?>
        <div class="management-card">
            <div class="card-header">
                <h4 class="card-title">
                    <span class="card-icon">🚀</span>
                    Projekty & Realizace
                </h4>
            </div>
            <div class="card-content">
                <?php if ($has_realizace_data || $realizace_count > 0): ?>
                    <!-- Future: When realizace system is implemented -->
                    <div class="realizace-summary">
                        <div class="summary-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo esc_html($realizace_count); ?></span>
                                <span class="stat-label">Celkem realizací</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo esc_html($pending_realizace); ?></span>
                                <span class="stat-label">Čeká na schválení</span>
                            </div>
                        </div>
                        <div class="card-actions">
                            <button type="button" 
                                    class="w-btn us-btn-style_1 view-realizace-btn" 
                                    data-user-id="<?php echo esc_attr((string)$user->ID); ?>">
                                <span class="button-icon">📋</span>
                                Zobrazit realizace
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Current: Before realizace system is implemented -->
                    <div class="feature-list">
                        <div class="feature-item coming-soon">
                            <span class="feature-icon">🏗️</span>
                            <span class="feature-text">Realizace</span>
                            <span class="coming-soon-badge">Připravujeme</span>
                        </div>
                        <div class="feature-item coming-soon">
                            <span class="feature-icon">📊</span>
                            <span class="feature-text">Přehledy</span>
                            <span class="coming-soon-badge">Připravujeme</span>
                        </div>
                        <div class="feature-item coming-soon">
                            <span class="feature-icon">📈</span>
                            <span class="feature-text">Statistiky</span>
                            <span class="coming-soon-badge">Připravujeme</span>
                        </div>
                    </div>
                    <p class="feature-description">
                        Zde se budou zobrazovat všechny projekty a realizace uživatele.
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get user realizace count (placeholder for future implementation)
     *
     * @param int $user_id User ID
     * @return int Number of realizace posts
     */
    private function get_user_realizace_count(int $user_id): int {
        // Future implementation will query realizace post type
        // For now, return 0 as placeholder
        return 0;
    }

    /**
     * Get pending realizace count for user (placeholder for future implementation)
     *
     * @param int $user_id User ID
     * @return int Number of pending realizace posts
     */
    private function get_pending_realizace_count(int $user_id): int {
        // Future implementation will query realizace posts with pending status
        // For now, return 0 as placeholder
        return 0;
    }

    /**
     * Render enhanced promote user button
     */
    private function render_promote_user_button_enhanced(int $user_id): void {
        $promote_url = add_query_arg([
            'action' => 'mistr_fachman_promote_user',
            'user_id' => $user_id,
            '_wpnonce' => wp_create_nonce('mistr_fachman_promote_user_' . $user_id)
        ], admin_url('admin-post.php'));
        
        ?>
        <a href="<?php echo esc_url($promote_url); ?>" 
           class="w-btn us-btn-style_1"
           onclick="return confirm('<?php esc_attr_e('Opravdu chcete povýšit tohoto uživatele na plného člena?', 'mistr-fachman'); ?>');">
            <span class="button-icon">✅</span>
            <?php esc_html_e('Schválit uživatele', 'mistr-fachman'); ?>
        </a>
        <?php
    }

    /**
     * Render enhanced revoke user button
     */
    private function render_revoke_user_button_enhanced(int $user_id): void {
        $revoke_url = add_query_arg([
            'action' => 'mistr_fachman_revoke_user',
            'user_id' => $user_id,
            '_wpnonce' => wp_create_nonce('mistr_fachman_revoke_user_' . $user_id)
        ], admin_url('admin-post.php'));
        
        ?>
        <a href="<?php echo esc_url($revoke_url); ?>" 
           class="w-btn us-btn-style_2"
           onclick="return confirm('<?php esc_attr_e('Opravdu chcete zrušit členství tohoto uživatele a vrátit ho do stavu čekání na schválení?', 'mistr-fachman'); ?>');">
            <span class="button-icon">↩️</span>
            <?php esc_html_e('Zrušit členství', 'mistr-fachman'); ?>
        </a>
        <?php
    }

    /**
     * Get description for registration status
     *
     * @param string $status Registration status
     * @param bool $is_pending Whether user is pending
     * @return string Status description
     */
    private function getStatusDescription(string $status, bool $is_pending = true): string {
        if (!$is_pending) {
            return 'Uživatel byl úspěšně schválen a má plný přístup ke všem funkcím.';
        }
        
        return match ($status) {
            RegistrationStatus::NEEDS_FORM => 'Uživatel se zaregistroval, ale ještě nevyplnil závěrečný formulář.',
            RegistrationStatus::AWAITING_REVIEW => 'Uživatel vyplnil formulář a čeká na schválení administrátorem.',
            RegistrationStatus::APPROVED => 'Uživatel byl schválen (legacy stav).',
            default => 'Neznámý stav registrace.'
        };
    }
}