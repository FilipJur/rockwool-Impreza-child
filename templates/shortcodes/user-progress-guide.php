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

        <!-- Progress Steps Container - Figma Layout: horizontal row with progress bars -->
        <div class="progress-steps-container flex items-center">
                
                <!-- Registration Step Container -->
                <div class="progress-step-wrapper flex items-center">
                    <!-- Step Badge with Icon and Text -->
                    <div class="step-badge-container flex items-center gap-3">
                        <?php if ($progress_data['registration']['completed']): ?>
                            <div class="step-badge-completed">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path fill-rule="evenodd" d="M13.366 4.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-3-3a1 1 0 011.414-1.414L6.659 9.586l5.293-5.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span><?= number_format($progress_data['registration']['points']) ?> bodů</span>
                            </div>
                        <?php else: ?>
                            <div class="step-badge-pending">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="#909090">
                                    <path fill-rule="evenodd" d="M8 15A7 7 0 108 1a7 7 0 000 14zm1-11a1 1 0 10-2 0v4a1 1 0 00.293.707l2.5 2.5a1 1 0 001.414-1.414L9 8.586V4z" clip-rule="evenodd"/>
                                </svg>
                                <span>
                                    <?php
                                    echo match($progress_data['user_status']) {
                                        'needs_form' => 'Dokončete registrační formulář',
                                        'awaiting_review' => 'Čeká na schválení',
                                        default => 'V procesu'
                                    };
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Step Title inline with badge -->
                        <h3 class="step-title">Dokončená registrace</h3>
                    </div>
                </div>

                <!-- First Progress Bar -->
                <div class="progress-bar-container">
                    <div class="progress-bar <?= $progress_data['registration']['completed'] ? 'completed' : 'pending' ?>"></div>
                </div>

                <!-- First Project Step Container -->
                <div class="progress-step-wrapper flex items-center">
                    <!-- Step Badge with Icon and Text -->
                    <div class="step-badge-container flex items-center gap-3">
                        <?php if ($progress_data['first_project']['status'] === 'published'): ?>
                            <div class="step-badge-completed">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path fill-rule="evenodd" d="M13.366 4.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-3-3a1 1 0 011.414-1.414L6.659 9.586l5.293-5.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span><?= number_format($progress_data['first_project']['points']) ?> bodů</span>
                            </div>
                        <?php elseif ($progress_data['first_project']['status'] === 'pending'): ?>
                            <div class="step-badge-in-progress">
                                <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18.6917 22.1654H6.69171V16.1654L10.6917 12.1654L6.69171 8.16541V2.16541H18.6917V8.16541L14.6917 12.1654L18.6917 16.1654M8.69171 7.66541L12.6917 11.6654L16.6917 7.66541V4.16541H8.69171M12.6917 12.6654L8.69171 16.6654V20.1654H16.6917V16.6654M14.6917 18.1654H10.6917V17.3654L12.6917 15.3654L14.6917 17.3654V18.1654Z" fill="black"/>
                                </svg>
                                <span><?= number_format($progress_data['first_project']['points']) ?> bodů</span>
                            </div>
                        <?php else: ?>
                            <div class="step-badge-none">
                                <span>Nahrajte první projekt</span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Step Title inline with badge -->
                        <h3 class="step-title step-title--project-<?= $progress_data['first_project']['status'] ?>">
                            <?php if ($progress_data['first_project']['status'] === 'published'): ?>
                                První nahraná realizace
                            <?php elseif ($progress_data['first_project']['status'] === 'pending'): ?>
                                Realizace čeká na schválení
                            <?php else: ?>
                                Nahrajte svou první realizaci
                            <?php endif; ?>
                        </h3>
                    </div>
                </div>

                <!-- Second Progress Bar -->
                <div class="progress-bar-container">
                    <div class="progress-bar <?= $progress_data['first_project']['completed'] ? 'completed' : ($progress_data['registration']['completed'] ? 'in-progress' : 'pending') ?>"></div>
                </div>

                <!-- Future Steps (Third step from Figma) -->
                <div class="progress-step-wrapper flex items-center">
                    <!-- Step Badge with Icon and Text -->
                    <div class="step-badge-container flex items-center gap-3">
                        <div class="step-badge-future">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="#909090">
                                <path fill-rule="evenodd" d="M4 7V5a4 4 0 018 0v2a2 2 0 012 2v5a2 2 0 01-2 2H4a2 2 0 01-2-2V9a2 2 0 012-2zm6-2v2H6V5a2 2 0 014 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        
                        <!-- Step Title inline with badge -->
                        <h3 class="step-title step-title--rewards-<?= $progress_data['first_project']['status'] === 'published' ? 'available' : 'waiting' ?>">
                            <?php if ($progress_data['first_project']['status'] === 'published'): ?>
                                Vyberte si první odměny
                            <?php else: ?>
                                Vyberte si první odměny za odvedenou práci
                            <?php endif; ?>
                        </h3>
                    </div>
                </div>

        </div>
    </div>
</div>