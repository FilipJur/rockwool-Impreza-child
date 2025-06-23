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

    /**
     * Render realizace overview card for user profile
     *
     * @param \WP_User $user User object
     */
    public function render_realizace_overview_card(\WP_User $user): void {
        $stats = $this->get_user_realizace_stats($user->ID);
        
        ?>
        <div class="management-card realizace-overview">
            <div class="card-header">
                <h4 class="card-title">
                    <span class="card-icon">📋</span>
                    Přehled realizací
                </h4>
                <div class="status-indicator status-info">
                    <span class="indicator-dot"></span>
                    <span class="indicator-text"><?php echo esc_html($stats['total']); ?> celkem</span>
                </div>
            </div>
            
            <div class="card-content">
                <div class="stats-grid">
                    <div class="stat-item stat-pending">
                        <div class="stat-number"><?php echo esc_html($stats['pending']); ?></div>
                        <div class="stat-label">Čeká na schválení</div>
                    </div>
                    
                    <div class="stat-item stat-approved">
                        <div class="stat-number"><?php echo esc_html($stats['approved']); ?></div>
                        <div class="stat-label">Schváleno</div>
                    </div>
                    
                    <div class="stat-item stat-rejected">
                        <div class="stat-number"><?php echo esc_html($stats['rejected']); ?></div>
                        <div class="stat-label">Odmítnuto</div>
                    </div>
                    
                    <div class="stat-item stat-points">
                        <div class="stat-number"><?php echo esc_html($stats['total_points']); ?></div>
                        <div class="stat-label">Získané body</div>
                    </div>
                </div>
                
                <?php if ($stats['total'] > 0): ?>
                <div class="card-actions">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=realizace&author=' . $user->ID)); ?>" 
                       class="action-button action-primary">
                        <span class="button-icon">🔍</span>
                        Zobrazit všechny realizace
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render recent realizace management card
     *
     * @param \WP_User $user User object
     */
    public function render_recent_realizace_card(\WP_User $user): void {
        $recent_posts = $this->get_recent_realizace($user->ID, 3);
        
        ?>
        <div class="management-card recent-realizace">
            <div class="card-header">
                <h4 class="card-title">
                    <span class="card-icon">⏰</span>
                    Nejnovější realizace
                </h4>
            </div>
            
            <div class="card-content">
                <?php if (empty($recent_posts)): ?>
                    <div class="empty-state">
                        <p class="empty-message">Uživatel zatím nepřidal žádné realizace.</p>
                    </div>
                <?php else: ?>
                    <div class="realizace-list">
                        <?php foreach ($recent_posts as $post): ?>
                            <div class="realizace-item" data-post-id="<?php echo esc_attr($post->ID); ?>">
                                <div class="item-header">
                                    <h5 class="item-title">
                                        <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                            <?php echo esc_html($post->post_title); ?>
                                        </a>
                                    </h5>
                                    <div class="item-status">
                                        <?php $this->render_status_badge($post->post_status); ?>
                                    </div>
                                </div>
                                
                                <div class="item-meta">
                                    <span class="meta-date">
                                        <?php echo esc_html(mysql2date('j.n.Y', $post->post_date)); ?>
                                    </span>
                                    
                                    <?php if ($post->post_status === 'publish'): ?>
                                        <?php $points = (int)get_post_meta($post->ID, '_realizace_points_awarded', true); ?>
                                        <?php if ($points > 0): ?>
                                            <span class="meta-points">
                                                <span class="points-icon">⭐</span>
                                                <?php echo esc_html($points); ?> bodů
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="item-actions">
                                    <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" 
                                       class="action-link action-edit">
                                        Upravit
                                    </a>
                                    
                                    <?php if ($post->post_status === 'pending'): ?>
                                        <div class="quick-points-wrapper">
                                            <input type="number" 
                                                   class="quick-points-input" 
                                                   placeholder="Body" 
                                                   data-post-id="<?php echo esc_attr($post->ID); ?>" 
                                                   style="width: 60px; height: 24px; text-align: right; margin-right: 5px;">
                                        </div>
                                        <button type="button" 
                                                class="action-link action-approve" 
                                                data-post-id="<?php echo esc_attr($post->ID); ?>"
                                                data-action="approve">
                                            Schválit
                                        </button>
                                        <button type="button" 
                                                class="action-link action-reject" 
                                                data-post-id="<?php echo esc_attr($post->ID); ?>"
                                                data-action="reject">
                                            Odmítnout
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render quick action card for realizace management
     *
     * @param \WP_User $user User object
     */
    public function render_quick_actions_card(\WP_User $user): void {
        $pending_count = $this->get_pending_realizace_count($user->ID);
        
        ?>
        <div class="management-card quick-actions">
            <div class="card-header">
                <h4 class="card-title">
                    <span class="card-icon">⚡</span>
                    Rychlé akce
                </h4>
            </div>
            
            <div class="card-content">
                <div class="action-buttons">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=realizace&author=' . $user->ID)); ?>" 
                       class="action-button action-secondary">
                        <span class="button-icon">📋</span>
                        Všechny realizace
                    </a>
                    
                    <?php if ($pending_count > 0): ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=realizace&post_status=pending&author=' . $user->ID)); ?>" 
                           class="action-button action-warning">
                            <span class="button-icon">⏳</span>
                            Čekající na schválení (<?php echo esc_html($pending_count); ?>)
                        </a>
                    <?php endif; ?>
                    
                    <button type="button" 
                            class="action-button action-info bulk-approve-btn"
                            data-user-id="<?php echo esc_attr($user->ID); ?>"
                            <?php echo $pending_count === 0 ? 'disabled' : ''; ?>>
                        <span class="button-icon">✅</span>
                        Hromadně schválit
                    </button>
                </div>
                
                <?php if ($pending_count > 0): ?>
                    <div class="action-notice">
                        <p class="notice-text">
                            <strong>Pozor:</strong> Uživatel má <?php echo esc_html($pending_count); ?> 
                            neschválených realizací čekajících na vyřízení.
                        </p>
                    </div>
                <?php endif; ?>
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
            WHERE p.post_type = 'realizace' 
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
            'post_type' => 'realizace',
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
     * Get count of pending realizace for user
     *
     * @param int $user_id User ID
     * @return int Pending count
     */
    private function get_pending_realizace_count(int $user_id): int {
        $query = new \WP_Query([
            'post_type' => 'realizace',
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
}