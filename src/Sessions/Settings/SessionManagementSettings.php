<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sessions\Settings;

use Codenzia\FilamentPanelBase\Sessions\SessionManagementPlugin;
use Spatie\LaravelSettings\Settings;

/**
 * Runtime-toggleable session management settings. Resolution order:
 * fluent API (SessionManagementPlugin) -> these settings -> hard-coded defaults.
 *
 * @see SessionManagementPlugin
 */
class SessionManagementSettings extends Settings
{
    /** Master switch — when false, the profile tab is hidden. */
    public bool $enabled = true;

    /**
     * When true, emit a NewDeviceLogin event the first time a (user, device
     * fingerprint) pair is seen. Hosts can listen and email the user.
     */
    public bool $notify_on_new_device = true;

    /** Inactivity threshold (in minutes) after which a session is shown as "Inactive" in the UI. */
    public int $idle_threshold_minutes = 30;

    /**
     * Whether to expose the "Sign out everywhere else" action. Disable for
     * environments where Auth::logoutOtherDevices() would conflict with a
     * custom session lifecycle.
     */
    public bool $allow_logout_other_devices = true;

    public static function group(): string
    {
        return 'session_management';
    }
}
