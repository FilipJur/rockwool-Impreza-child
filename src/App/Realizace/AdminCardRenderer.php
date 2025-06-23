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
                                data-user-id="<?php echo esc_attr($user->ID); ?>">
                            Hromadně schválit (<?php echo esc_html($pending_count); ?>)
                        </button>
                    <?php endif; ?>
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
        $recent_posts = $this->get_recent_realizace($user->ID, 6);
        
        ?>
        <div class="management-card">
            <div class="card-header">
                <h4 class="card-title">
                    Nejnovější realizace
                </h4>
            </div>
            
            <div class="card-content">
                <?php if (empty($recent_posts)): ?>
                    <div class="empty-state">
                        <p class="empty-text">Žádné realizace</p>
                    </div>
                <?php else: ?>
                    <div class="realizace-list">
                    <?php foreach ($recent_posts as $post): ?>
                        <?php 
                        $featured_image = get_the_post_thumbnail_url($post->ID, 'large');
                        $placeholder_image = 'data:image/svg+xml;base64,' . base64_encode('<svg width="400" height="240" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#f3f4f6"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#9ca3af">📷</text></svg>');
                        $assigned_points = function_exists('get_field') 
                            ? get_field('pridelene_body', $post->ID) 
                            : get_post_meta($post->ID, 'pridelene_body', true);
                        $awarded_points = (int)get_post_meta($post->ID, '_realizace_points_awarded', true);
                        $rejection_reason = function_exists('get_field') 
                            ? get_field('duvod_zamitnuti', $post->ID) 
                            : get_post_meta($post->ID, 'duvod_zamitnuti', true);
                        $gallery_images = function_exists('get_field') 
                            ? get_field('fotky_realizace', $post->ID) 
                            : get_post_meta($post->ID, 'fotky_realizace', true);
                        $pocet_m2 = function_exists('get_field') 
                            ? get_field('pocet_m2', $post->ID) 
                            : get_post_meta($post->ID, 'pocet_m2', true);
                        $typ_konstrukce = function_exists('get_field') 
                            ? get_field('typ_konstrukce', $post->ID) 
                            : get_post_meta($post->ID, 'typ_konstrukce', true);
                        $pouzite_materialy = function_exists('get_field') 
                            ? get_field('pouzite_materialy', $post->ID) 
                            : get_post_meta($post->ID, 'pouzite_materialy', true);
                        ?>
                        <div class="realizace-item" data-post-id="<?php echo esc_attr($post->ID); ?>" data-status="<?php echo esc_attr($post->post_status); ?>">
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
                                        <span class="points-awarded">Uděleno: <?php echo esc_html($awarded_points); ?> bodů</span>
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
                                            <span class="detail-value"><?php echo esc_html($pocet_m2); ?> m²</span>
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
                                               data-post-id="<?php echo esc_attr($post->ID); ?>" 
                                               value="<?php echo esc_attr($assigned_points ?: ''); ?>"
                                               style="width: 60px;">
                                        <button type="button" 
                                                class="button button-primary action-approve" 
                                                data-post-id="<?php echo esc_attr($post->ID); ?>"
                                                data-action="approve">Schválit</button>
                                    </div>
                                    <div class="rejection-input">
                                        <textarea class="rejection-reason-input" 
                                                  data-post-id="<?php echo esc_attr($post->ID); ?>"
                                                  placeholder="Důvod zamítnutí..."
                                                  rows="2" style="width: 100%; margin-bottom: 5px;"></textarea>
                                        <button type="button" 
                                                class="button action-reject" 
                                                data-post-id="<?php echo esc_attr($post->ID); ?>"
                                                data-action="reject">Odmítnout</button>
                                    </div>
                                </div>
                                
                            <?php elseif ($post->post_status === 'publish'): ?>
                                <div class="realizace-actions">
                                    <button type="button" 
                                            class="button action-reject" 
                                            data-post-id="<?php echo esc_attr($post->ID); ?>"
                                            data-action="reject">Zrušit schválení</button>
                                </div>
                                
                            <?php elseif ($post->post_status === 'rejected'): ?>
                                <div class="realizace-actions">
                                    <label>Body: </label>
                                    <input type="number" 
                                           class="quick-points-input small-text" 
                                           placeholder="0" 
                                           data-post-id="<?php echo esc_attr($post->ID); ?>" 
                                           value="<?php echo esc_attr($assigned_points ?: ''); ?>"
                                           style="width: 60px;">
                                    <button type="button" 
                                            class="button button-primary action-approve" 
                                            data-post-id="<?php echo esc_attr($post->ID); ?>"
                                            data-action="approve">Schválit</button>
                                    
                                    <div style="margin-top: 10px;">
                                        <textarea class="rejection-reason-input" 
                                                  data-post-id="<?php echo esc_attr($post->ID); ?>"
                                                  placeholder="Upravit důvod odmítnutí..."
                                                  rows="2" style="width: 100%; margin-bottom: 5px;"><?php echo esc_textarea($rejection_reason); ?></textarea>
                                        <button type="button" 
                                                class="button save-rejection-btn" 
                                                data-post-id="<?php echo esc_attr($post->ID); ?>">Uložit důvod</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
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