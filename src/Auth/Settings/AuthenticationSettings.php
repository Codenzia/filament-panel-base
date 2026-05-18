<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Runtime-toggleable authentication & signup settings. All fields can be
 * overridden by the AuthenticationPlugin fluent API at panel-registration
 * time. Resolution order: fluent API → these settings → config defaults.
 *
 * @see \Codenzia\FilamentPanelBase\Auth\AuthenticationPlugin
 */
class AuthenticationSettings extends Settings
{
    /** 'open' = immediate access, 'moderated' = admin approval required */
    public string $registration_mode = 'open';

    /** Require users to verify their email address before accessing the platform */
    public bool $require_email_verification = true;

    /** Require users to verify their phone number before accessing the platform */
    public bool $require_phone_verification = false;

    /** Which credentials are collected at signup: 'email', 'phone', or 'both'. */
    public string $credentials_mode = 'email';

    /** Whether the signup form requires a phone (in addition to email when credentials_mode='both'). */
    public bool $phone_required = false;

    /** Default OTP driver: 'email' | 'whatsapp' | 'twilio' | 'vonage' | 'null' */
    public string $otp_driver = 'email';

    /**
     * Drivers admins are allowed to switch to from the settings UI.
     *
     * @var array<int, string>
     */
    public array $allowed_otp_drivers = ['email', 'whatsapp', 'twilio', 'vonage', 'null'];

    /**
     * Enabled Socialite providers (e.g. ['google', 'facebook']).
     *
     * @var array<int, string>
     */
    public array $social_providers_enabled = [];

    /**
     * How to handle a social sign-in whose email matches an existing user
     * that has not previously linked this provider:
     *
     *  - 'require_login'  (default, safe): block the auto-link, prompt the
     *    user to sign in with the original method and then connect from
     *    their profile.
     *  - 'trust_verified': link only when BOTH the existing user has
     *    `email_verified_at` set AND the provider returned a verified email.
     *  - 'auto': unconditional link — convenient but enables account takeover
     *    if any provider returns an unverified email. Opt-in only.
     */
    public string $social_email_linking = 'require_login';

    /**
     * When `true`, treat a provider-asserted verified email as sufficient
     * to mark `users.email_verified_at` at social signup. When `false`,
     * social signups still go through Laravel's email verification flow.
     */
    public bool $social_trust_verified_email = true;

    /** Reject signups whose email domain is in the disposable-email blocklist. */
    public bool $disposable_email_blocking = true;

    /** Per-minute rate limit for auth endpoints (login, register, OTP send, OTP verify). */
    public int $throttle_per_minute = 5;

    /** Per-day rate limit for auth endpoints. */
    public int $throttle_per_day = 50;

    /** E.164 country code prefix used as the default selection on the phone input. */
    public string $default_country_code = '+1';

    /** OTP code length (digits). */
    public int $otp_code_length = 6;

    /** OTP code lifetime in minutes. */
    public int $otp_ttl_minutes = 10;

    public static function group(): string
    {
        return 'auth';
    }
}
