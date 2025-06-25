<?php

declare(strict_types=1);

namespace MistrFachman\Realizace;

/**
 * Realizace Admin Card Renderer - User Profile Integration
 *
 * Renders realizace management cards in user profile pages.
 * Integrates with the existing Users domain admin interface.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminCardRenderer {

    private ?AdminController $admin_controller = null;

    /**
     * Set the admin controller for field access
     */
    public function setAdminController(AdminController $admin_controller): void {
        $this->admin_controller = $admin_controller;
    }

    /**
     * Get points value for a realizace post using centralized field access
     */
    private function get_realizace_points(int $post_id): int {
        // Use admin controller's abstract method if available, fallback to field service
        if ($this->admin_controller) {
            return $this->admin_controller->get_current_points($post_id);
        }
        
        // Use centralized field service
        return RealizaceFieldService::getPoints($post_id);
    }

    /**
     * Render realizace overview card for user profile
     *
     * @param \WP_User $user User object
     */
    public function render_realizace_overview_card(\WP_User $user): void {
        $stats = $this->get_user_realizace_stats($user->ID);

        ?>
        <div class="management-card">
            <div class="card-header">
                <h4 class="card-title">
                    Přehled realizací
                </h4>
            </div>

            <div class="card-content">
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label">Celkem:</span>
                        <span class="summary-value"><?php echo esc_html($stats['total']); ?></span>
                    </div>
                    <div class="summary-item status-pending">
                        <span class="summary-label">Čeká:</span>
                        <span class="summary-value"><?php echo esc_html($stats['pending']); ?></span>
                    </div>
                    <div class="summary-item status-approved">
                        <span class="summary-label">Schváleno:</span>
                        <span class="summary-value"><?php echo esc_html($stats['approved']); ?></span>
                    </div>
                    <div class="summary-item status-rejected">
                        <span class="summary-label">Odmítnuto:</span>
                        <span class="summary-value"><?php echo esc_html($stats['rejected']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Body celkem:</span>
                        <span class="summary-value"><?php echo esc_html($stats['total_points']); ?></span>
                    </div>
                </div>

                <?php if ($stats['total'] > 0): ?>
                <div class="card-actions">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=realizace&author=' . $user->ID)); ?>"
                       class="button button-secondary">Zobrazit všechny</a>

                    <?php $pending_count = $this->get_pending_realizace_count($user->ID); ?>
                    <?php if ($pending_count > 0): ?>
                        <button type="button"
                                class="button button-primary bulk-approve-btn"
                                data-user-id="<?php echo esc_attr((string)$user->ID); ?>">
                            Hromadně schválit (<?php echo esc_html((string)$pending_count); ?>)
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render consolidated realizace management dashboard
     *
     * Displays realizace grouped by status with integrated stats header.
     * Only shows sections that contain items (except completed which is always shown but collapsed).
     *
     * @param \WP_User $user User object
     */
    public function render_consolidated_realizace_dashboard(\WP_User $user): void {
        $stats = $this->get_user_realizace_stats($user->ID);
        $realizace_by_status = $this->get_realizace_grouped_by_status($user->ID);

        ?>
        <div class="management-card realizace-dashboard">
            <div class="card-header">
                <h4 class="card-title">Správa realizací</h4>

                <!-- Integrated Stats Header -->
                <div class="stats-header">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-label">Celkem:</span>
                            <span class="stat-value"><?php echo esc_html((string)$stats['total']); ?></span>
                        </div>
                        <?php if ($stats['pending'] > 0): ?>
                            <div class="stat-item stat-pending">
                                <span class="stat-label">Čeká:</span>
                                <span class="stat-value"><?php echo esc_html((string)$stats['pending']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($stats['approved'] > 0): ?>
                            <div class="stat-item stat-approved">
                                <span class="stat-label">Schváleno:</span>
                                <span class="stat-value"><?php echo esc_html((string)$stats['approved']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($stats['rejected'] > 0): ?>
                            <div class="stat-item stat-rejected">
                                <span class="stat-label">Odmítnuto:</span>
                                <span class="stat-value"><?php echo esc_html((string)$stats['rejected']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="stat-item stat-points">
                            <span class="stat-label">Body celkem:</span>
                            <span class="stat-value"><?php echo esc_html((string)$stats['total_points']); ?></span>
                        </div>
                    </div>

                    <?php if ($stats['total'] > 0): ?>
                        <div class="stats-actions">
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=realizace&author=' . $user->ID)); ?>"
                               class="button button-secondary button-small">Zobrazit všechny</a>

                            <?php if ($stats['pending'] > 0): ?>
                                <button type="button"
                                        class="button button-primary button-small bulk-approve-btn"
                                        data-user-id="<?php echo esc_attr((string)$user->ID); ?>">
                                    Hromadně schválit (<?php echo esc_html((string)$stats['pending']); ?>)
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-content">
                <?php if ($stats['total'] === 0): ?>
                    <div class="empty-state">
                        <p class="empty-text">Žádné realizace</p>
                    </div>
                <?php else: ?>

                    <!-- PENDING SECTION (High Priority) -->
                    <?php if (!empty($realizace_by_status['pending'])): ?>
                        <div class="realizace-section section-pending" data-status="pending">
                            <div class="section-header">
                                <h5 class="section-title">
                                    <span class="status-indicator pending">●</span>
                                    Čeká na schválení (<?php echo esc_html((string)count($realizace_by_status['pending'])); ?>)
                                </h5>
                            </div>
                            <div class="section-content">
                                <div class="realizace-list">
                                    <?php foreach ($realizace_by_status['pending'] as $post): ?>
                                        <?php $this->render_realizace_card($post); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- REJECTED SECTION (Medium Priority) -->
                    <?php if (!empty($realizace_by_status['rejected'])): ?>
                        <div class="realizace-section section-rejected" data-status="rejected">
                            <div class="section-header">
                                <h5 class="section-title">
                                    <span class="status-indicator rejected">●</span>
                                    Odmítnuto (<?php echo esc_html((string)count($realizace_by_status['rejected'])); ?>)
                                </h5>
                                <button type="button" class="section-toggle" data-target="section-rejected">
                                    <span class="toggle-icon">▼</span>
                                </button>
                            </div>
                            <div class="section-content collapsible" data-section="rejected">
                                <div class="realizace-list">
                                    <?php foreach ($realizace_by_status['rejected'] as $post): ?>
                                        <?php $this->render_realizace_card($post); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- COMPLETED SECTION (Low Priority - Always shown but collapsed) -->
                    <?php if (!empty($realizace_by_status['approved'])): ?>
                        <div class="realizace-section section-approved" data-status="approved">
                            <div class="section-header">
                                <h5 class="section-title">
                                    <span class="status-indicator approved">●</span>
                                    Schváleno (<?php echo esc_html((string)count($realizace_by_status['approved'])); ?>)
                                </h5>
                                <button type="button" class="section-toggle" data-target="section-approved">
                                    <span class="toggle-icon">▶</span>
                                </button>
                            </div>
                            <div class="section-content collapsible collapsed" data-section="approved">
                                <div class="realizace-list realizace-list-compact">
                                    <?php foreach ($realizace_by_status['approved'] as $post): ?>
                                        <?php $this->render_realizace_card_compact($post); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render individual realizace card (full version)
     *
     * @param \WP_Post $post Post object
     */
    private function render_realizace_card(\WP_Post $post): void {
        // Get all the data needed for rendering
        $assigned_points = $this->get_realizace_points($post->ID);
        $awarded_points = (int)get_post_meta($post->ID, '_realizace_points_awarded', true);
        $rejection_reason = RealizaceFieldService::getRejectionReason($post->ID);
        $gallery_images = RealizaceFieldService::getGallery($post->ID);
        $pocet_m2 = RealizaceFieldService::getArea($post->ID);
        $typ_konstrukce = RealizaceFieldService::getConstructionType($post->ID);
        $pouzite_materialy = RealizaceFieldService::getMaterials($post->ID);
        
        ?>
        <div class="realizace-item" data-post-id="<?php echo esc_attr((string)$post->ID); ?>" data-status="<?php echo esc_attr($post->post_status); ?>">
            <div class="realizace-header">
                <div class="realizace-title">
                    <strong><a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a></strong>
                    <span class="status-badge status-<?php echo esc_attr($post->post_status); ?>"><?php echo esc_html($this->get_status_config($post->post_status)['label']); ?></span>
                </div>
                <div class="realizace-meta">
                    <span class="post-date"><?php echo esc_html(mysql2date('j.n.Y H:i', $post->post_date)); ?></span>
                    <?php if ($assigned_points): ?>
                        <span class="points-assigned">Přiděleno: <?php echo esc_html($assigned_points); ?> bodů</span>
                    <?php endif; ?>
                    <?php if ($awarded_points > 0): ?>
                        <span class="points-awarded">Uděleno: <?php echo esc_html((string)$awarded_points); ?> bodů</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($post->post_content): ?>
                <div class="realizace-excerpt">
                    <?php echo esc_html(wp_trim_words($post->post_content, 20, '...')); ?>
                </div>
            <?php endif; ?>

            <?php if ($pocet_m2 || $typ_konstrukce || $pouzite_materialy): ?>
                <div class="realizace-details">
                    <?php if ($pocet_m2): ?>
                        <div class="detail-item">
                            <span class="detail-label">Plocha:</span>
                            <span class="detail-value"><?php echo esc_html((string)$pocet_m2); ?> m²</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($typ_konstrukce): ?>
                        <div class="detail-item">
                            <span class="detail-label">Konstrukce:</span>
                            <span class="detail-value"><?php echo esc_html(wp_trim_words($typ_konstrukce, 8, '...')); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($pouzite_materialy): ?>
                        <div class="detail-item">
                            <span class="detail-label">Materiály:</span>
                            <span class="detail-value"><?php echo esc_html(wp_trim_words($pouzite_materialy, 8, '...')); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($gallery_images) && is_array($gallery_images)): ?>
                <div class="realizace-gallery">
                    <?php foreach (array_slice($gallery_images, 0, 3) as $image): ?>
                        <?php
                        $image_url = '';
                        $image_alt = '';

                        if (is_array($image) && isset($image['sizes']['thumbnail'])) {
                            // ACF image array format
                            $image_url = $image['sizes']['thumbnail'];
                            $image_alt = $image['alt'] ?? '';
                        } elseif (is_numeric($image)) {
                            // Image ID format
                            $image_url = wp_get_attachment_image_url($image, 'thumbnail');
                            $image_alt = get_post_meta($image, '_wp_attachment_image_alt', true);
                        }

                        if ($image_url):
                        ?>
                            <img src="<?php echo esc_url($image_url); ?>"
                                 alt="<?php echo esc_attr($image_alt); ?>"
                                 class="gallery-thumb">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (count($gallery_images) > 3): ?>
                        <span class="gallery-more">+<?php echo count($gallery_images) - 3; ?> dalších</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($post->post_status === 'rejected' && $rejection_reason): ?>
                <div class="rejection-reason">
                    <strong>Důvod odmítnutí:</strong> <?php echo esc_html($rejection_reason); ?>
                </div>
            <?php endif; ?>

            <?php if ($post->post_status === 'pending'): ?>
                <div class="realizace-actions">
                    <div class="points-input">
                        <label>Body: </label>
                        <input type="number"
                               class="quick-points-input small-text"
                               placeholder="0"
                               data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                               value="<?php echo esc_attr($assigned_points ?: ''); ?>"
                               style="width: 60px;">
                        <button type="button"
                                class="button button-primary action-approve"
                                data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                                data-action="approve">Schválit</button>
                    </div>
                    <div class="rejection-input">
                        <textarea class="rejection-reason-input"
                                  data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                                  placeholder="Důvod zamítnutí..."
                                  rows="2" style="width: 100%; margin-bottom: 5px;"></textarea>
                        <button type="button"
                                class="button action-reject"
                                data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                                data-action="reject">Odmítnout</button>
                    </div>
                </div>

            <?php elseif ($post->post_status === 'publish'): ?>
                <div class="realizace-actions">
                    <button type="button"
                            class="button action-reject"
                            data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                            data-action="reject">Zrušit schválení</button>
                </div>

            <?php elseif ($post->post_status === 'rejected'): ?>
                <div class="realizace-actions">
                    <label>Body: </label>
                    <input type="number"
                           class="quick-points-input small-text"
                           placeholder="0"
                           data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                           value="<?php echo esc_attr($assigned_points ?: ''); ?>"
                           style="width: 60px;">
                    <button type="button"
                            class="button button-primary action-approve"
                            data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                            data-action="approve">Schválit</button>

                    <div style="margin-top: 10px;">
                        <textarea class="rejection-reason-input"
                                  data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                                  placeholder="Upravit důvod odmítnutí..."
                                  rows="2" style="width: 100%; margin-bottom: 5px;"><?php echo esc_textarea($rejection_reason); ?></textarea>
                        <button type="button"
                                class="button save-rejection-btn"
                                data-post-id="<?php echo esc_attr((string)$post->ID); ?>">Uložit důvod</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render compact realizace card for completed section
     *
     * @param \WP_Post $post Post object
     */
    private function render_realizace_card_compact(\WP_Post $post): void {
        $awarded_points = (int)get_post_meta($post->ID, '_realizace_points_awarded', true);

        ?>
        <div class="realizace-item-compact" data-post-id="<?php echo esc_attr((string)$post->ID); ?>">
            <div class="compact-content">
                <div class="compact-title">
                    <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a>
                </div>
                <div class="compact-meta">
                    <span class="compact-date"><?php echo esc_html(mysql2date('j.n.Y', $post->post_date)); ?></span>
                    <?php if ($awarded_points > 0): ?>
                        <span class="compact-points"><?php echo esc_html((string)$awarded_points); ?> bodů</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="compact-actions">
                <button type="button"
                        class="button button-small action-reject"
                        data-post-id="<?php echo esc_attr((string)$post->ID); ?>"
                        data-action="reject">Zrušit</button>
            </div>
        </div>
        <?php
    }

    /**
     * Render status badge for realizace
     *
     * @param string $status Post status
     */
    private function render_status_badge(string $status): void {
        $config = $this->get_status_config($status);

        ?>
        <span class="status-badge status-<?php echo esc_attr($status); ?>">
            <span class="badge-indicator <?php echo esc_attr($config['class']); ?>"></span>
            <span class="badge-text"><?php echo esc_html($config['label']); ?></span>
        </span>
        <?php
    }


    /**
     * Get user realizace statistics
     *
     * @param int $user_id User ID
     * @return array Statistics
     */
    private function get_user_realizace_stats(int $user_id): array {
        global $wpdb;

        $stats = $wpdb->get_results($wpdb->prepare("
            SELECT
                post_status,
                COUNT(*) as count,
                COALESCE(SUM(CAST(pm.meta_value AS SIGNED)), 0) as total_points
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_realizace_points_awarded')
            WHERE p.post_type = 'realization'
            AND p.post_author = %d
            AND p.post_status IN ('pending', 'publish', 'rejected')
            GROUP BY post_status
        ", $user_id), ARRAY_A);

        $result = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'total_points' => 0
        ];

        foreach ($stats as $stat) {
            $result['total'] += (int)$stat['count'];

            switch ($stat['post_status']) {
                case 'pending':
                    $result['pending'] = (int)$stat['count'];
                    break;
                case 'publish':
                    $result['approved'] = (int)$stat['count'];
                    $result['total_points'] += (int)$stat['total_points'];
                    break;
                case 'rejected':
                    $result['rejected'] = (int)$stat['count'];
                    break;
            }
        }

        return $result;
    }

    /**
     * Get recent realizace posts for user
     *
     * @param int $user_id User ID
     * @param int $limit Number of posts to retrieve
     * @return \WP_Post[] Recent posts
     */
    private function get_recent_realizace(int $user_id, int $limit = 5): array {
        $query = new \WP_Query([
            'post_type' => 'realization',
            'author' => $user_id,
            'post_status' => ['pending', 'publish', 'rejected'],
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => true
        ]);

        return $query->posts;
    }

    /**
     * Get realizace grouped by status for consolidated dashboard
     *
     * @param int $user_id User ID
     * @return array Realizace grouped by status
     */
    private function get_realizace_grouped_by_status(int $user_id): array {
        $query = new \WP_Query([
            'post_type' => 'realization',
            'author' => $user_id,
            'post_status' => ['pending', 'publish', 'rejected'],
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => true
        ]);

        $grouped = [
            'pending' => [],
            'approved' => [],
            'rejected' => []
        ];

        foreach ($query->posts as $post) {
            switch ($post->post_status) {
                case 'pending':
                    $grouped['pending'][] = $post;
                    break;
                case 'publish':
                    $grouped['approved'][] = $post;
                    break;
                case 'rejected':
                    $grouped['rejected'][] = $post;
                    break;
            }
        }

        return $grouped;
    }

    /**
     * Get count of pending realizace for user
     *
     * @param int $user_id User ID
     * @return int Pending count
     */
    private function get_pending_realizace_count(int $user_id): int {
        $query = new \WP_Query([
            'post_type' => 'realization',
            'author' => $user_id,
            'post_status' => 'pending',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false
        ]);

        return $query->found_posts;
    }

    /**
     * Get status configuration for styling
     *
     * @param string $status Post status
     * @return array Configuration
     */
    private function get_status_config(string $status): array {
        return match ($status) {
            'publish' => [
                'label' => 'Schváleno',
                'class' => 'status-success'
            ],
            'pending' => [
                'label' => 'Čeká na schválení',
                'class' => 'status-warning'
            ],
            'rejected' => [
                'label' => 'Odmítnuto',
                'class' => 'status-error'
            ],
            'draft' => [
                'label' => 'Koncept',
                'class' => 'status-draft'
            ],
            default => [
                'label' => ucfirst($status),
                'class' => 'status-default'
            ]
        };
    }

    /**
     * Get status emoji for visual indication
     *
     * @param string $status Post status
     * @return string Emoji representing the status
     */
    private function get_status_emoji(string $status): string {
        return match ($status) {
            'publish' => '●',
            'pending' => '●',
            'rejected' => '●',
            'draft' => '●',
            default => '●'
        };
    }

}
