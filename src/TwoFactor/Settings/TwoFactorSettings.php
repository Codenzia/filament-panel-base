<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Settings;

use Codenzia\FilamentPanelBase\TwoFactor\TwoFactorPlugin;
use Spatie\LaravelSettings\Settings;

/**
 * Runtime-toggleable two-factor authentication settings. Resolution order:
 * fluent API (TwoFactorPlugin) -> these settings -> hard-coded defaults.
 *
 * @see TwoFactorPlugin
 */
class TwoFactorSettings extends Settings
{
    /** Master switch — when false, the challenge flow is skipped entirely. */
    public bool $enabled = true;

    /** Issuer name shown in the authenticator app entry (defaults to app name at render). */
    public ?string $issuer = null;

    /** Number of recovery codes generated per user. */
    public int $recovery_code_count = 8;

    /** TOTP digit length (6 = Google Authenticator default, 8 = enterprise tokens). */
    public int $digits = 6;

    /** TOTP step in seconds (30 = RFC default). */
    public int $period = 30;

    /** Acceptance window — how many ±step codes are accepted (1 = ±30s, 2 = ±60s). */
    public int $window = 1;

    /**
     * Role names whose members MUST enroll in 2FA before reaching any
     * Filament page. Empty array = 2FA stays per-user opt-in.
     *
     * @var array<int, string>
     */
    public array $require_for_roles = [];

    /**
     * Whether to remember a successful 2FA challenge in a long-lived cookie
     * so the user is not re-challenged on every login from the same browser.
     */
    public bool $remember_device = true;

    /** Cookie lifetime for remember-device, in days. */
    public int $remember_device_days = 30;

    public static function group(): string
    {
        return 'two_factor';
    }
}
