<?php

namespace Codenzia\FilamentPanelBase\Contracts;

/**
 * Contract for user models that support moderation (approval/suspension).
 *
 * Implement this interface on your User model to use the EnsureUserApproved middleware.
 */
interface HasModerationStatus
{
    /**
     * Check if the user account is suspended.
     */
    public function isSuspended(): bool;

    /**
     * Check if the user account is pending approval.
     */
    public function isPending(): bool;
}
