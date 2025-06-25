<?php

declare(strict_types=1);

namespace MistrFachman\Faktury;

use MistrFachman\Base\StatusManagerBase;

/**
 * Faktury Status Manager - Domain-Specific Status Operations
 *
 * Extends StatusManagerBase to provide faktury-specific status management.
 * Handles the 'rejected' status for faktura posts.
 * Mirrors Realizace StatusManager pattern exactly.
 *
 * @package mistr-fachman
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class StatusManager extends StatusManagerBase {

    /**
     * Get the post type slug for this domain
     */
    protected function getPostType(): string {
        return 'faktura';
    }

    /**
     * Get the custom status slug to register
     */
    protected function getCustomStatusSlug(): string {
        return 'rejected';
    }

    /**
     * Get the display label for the custom status
     */
    protected function getCustomStatusLabel(): string {
        return 'Odmítnuto';
    }

    /**
     * Register the rejected status (delegates to base class)
     */
    public function register_rejected_status(): void {
        $this->register_custom_status();
    }

    /**
     * Verify rejected status (delegates to base class)
     */
    public function verify_rejected_status(): void {
        $this->verify_custom_status();
    }

    /**
     * Check if rejected status is available for post updates
     */
    public function is_rejected_status_available(): bool {
        return $this->is_custom_status_available();
    }

    /**
     * Emergency re-registration of rejected status
     */
    public function emergency_register_rejected_status(): bool {
        return $this->emergency_register_custom_status();
    }
}