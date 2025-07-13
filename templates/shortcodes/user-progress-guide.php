<?php
/**
 * Template for User Progress Guide Shortcode
 *
 * This template displays the user's registration and project upload progress
 * with a modern, Figma-based design using Tailwind CSS.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract template data
$progress_data = $data['progress_data'] ?? [];
$attributes = $data['attributes'] ?? [];
$wrapper_classes = $data['wrapper_classes'] ?? '';
$user_id = $data['user_id'] ?? 0;

// Template is working correctly
?>

<div class="<?= esc_attr($wrapper_classes) ?> user-progress-guide">
    <!-- Main Guide Container - Figma Layout: column, gap:20px, padding:20px 30px 28px -->
    <div class="progress-guide-container bg-white shadow-card flex flex-col gap-5 w-full">
        
        <!-- Header Section -->
        <div class="progress-guide-header">
            <h2 class="progress-guide-title">A je to! Co teď?</h2>
        </div>

        <!-- Progress Steps Container - CSS-driven with data attributes -->
        <div class="progress-steps-container flex items-center">
                
                <!-- Registration Step -->
                <div class="progress-step-wrapper flex items-center" 
                     data-step="registration" 
                     data-state="<?= esc_attr($progress_data['registration']['state']) ?>"
                     data-current="<?= $progress_data['registration']['is_current'] ? 'true' : 'false' ?>">
                    
                    <div class="step-badge-container flex items-center gap-3">
                        <div class="step-badge">
                            <span class="step-badge__text"><?= esc_html($progress_data['registration']['text']) ?></span>
                        </div>
                        <h3 class="step-title"><?= esc_html($progress_data['registration']['title']) ?></h3>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-bar-container">
                    <div class="progress-bar" data-from="registration" data-to="project"></div>
                </div>

                <!-- Project Step -->
                <div class="progress-step-wrapper flex items-center"
                     data-step="project" 
                     data-state="<?= esc_attr($progress_data['project']['state']) ?>"
                     data-current="<?= $progress_data['project']['is_current'] ? 'true' : 'false' ?>">
                    
                    <div class="step-badge-container flex items-center gap-3">
                        <div class="step-badge">
                            <?php if ($progress_data['project']['text']): ?>
                                <span class="step-badge__text"><?= esc_html($progress_data['project']['text']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="step-title"><?= esc_html($progress_data['project']['title']) ?></h3>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-bar-container">
                    <div class="progress-bar" data-from="project" data-to="rewards"></div>
                </div>

                <!-- Rewards Step -->
                <div class="progress-step-wrapper flex items-center"
                     data-step="rewards" 
                     data-state="<?= esc_attr($progress_data['rewards']['state']) ?>"
                     data-current="<?= $progress_data['rewards']['is_current'] ? 'true' : 'false' ?>">
                    
                    <div class="step-badge-container flex items-center gap-3">
                        <div class="step-badge">
                            <!-- Rewards always show empty badge with icon in CSS -->
                        </div>
                        <h3 class="step-title"><?= esc_html($progress_data['rewards']['title']) ?></h3>
                    </div>
                </div>

        </div>
    </div>
</div>