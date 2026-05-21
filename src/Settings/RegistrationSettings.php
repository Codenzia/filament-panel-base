<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Settings;

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Spatie\LaravelSettings\Settings;

/**
 * @deprecated since 2.0 — use {@see AuthenticationSettings} instead.
 *
 * Retained for backward compatibility with consumers that import this
 * class directly. The two settings groups (`registration` and `auth`)
 * coexist; this class still reads/writes its original `registration.*`
 * keys, but new code should target AuthenticationSettings.
 *
 * The aqarkom data migration moves admin-configured values into the new
 * `auth.*` group during `filament-panel-base:install-auth`.
 */
class RegistrationSettings extends Settings
{
    /** 'open' = immediate access, 'moderated' = admin approval required */
    public string $registration_mode = 'open';

    /** Require users to verify their email address before accessing the platform */
    public bool $require_email_verification = true;

    public static function group(): string
    {
        return 'registration';
    }
}
