<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth;

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;

/**
 * Fluent configurator for the Auth module. Reached via
 * `FilamentPanelBasePlugin::make()->withAuthentication(fn ($auth) => $auth->...)`.
 *
 * Fluent values are applied to the AuthenticationSettings singleton at the
 * time `apply()` is called, taking precedence over DB-stored values for the
 * remainder of the request. They never write back to the database — admins
 * who want persistent changes use the Settings UI.
 *
 * @see FilamentPanelBasePlugin::withAuthentication()
 */
class AuthenticationPlugin
{
    private bool $enabled = false;

    private bool $modulePagesEnabled = false;

    private bool $modulePagesLogin = false;

    private bool $modulePagesRegister = false;

    /** @var array<string, mixed> */
    private array $overrides = [];

    /**
     * @param  string  ...$modes  One or more of 'email', 'phone'. Two values => 'both'.
     */
    public function credentials(string ...$modes): static
    {
        $clean = array_values(array_unique(array_map(static fn (string $mode) => strtolower($mode), $modes)));

        $this->overrides['credentials_mode'] = match (true) {
            count($clean) >= 2 => 'both',
            $clean === ['phone'] => 'phone',
            default => 'email',
        };

        return $this;
    }

    public function phoneRequired(bool $required = true): static
    {
        $this->overrides['phone_required'] = $required;

        return $this;
    }

    public function moderation(bool $enabled = true): static
    {
        $this->overrides['registration_mode'] = $enabled ? 'moderated' : 'open';

        return $this;
    }

    public function requireEmailVerification(bool $required = true): static
    {
        $this->overrides['require_email_verification'] = $required;

        return $this;
    }

    public function requirePhoneVerification(bool $required = true): static
    {
        $this->overrides['require_phone_verification'] = $required;

        return $this;
    }

    /**
     * @param  array<int, string>|null  $allowed
     */
    public function verification(?string $driver = null, ?array $allowed = null): static
    {
        if ($driver !== null) {
            $this->overrides['otp_driver'] = $driver;
        }

        if ($allowed !== null) {
            $this->overrides['allowed_otp_drivers'] = $allowed;
        }

        return $this;
    }

    /**
     * @param  array<int, string>  $providers
     */
    public function social(array $providers): static
    {
        $this->overrides['social_providers_enabled'] = array_values(array_unique($providers));

        return $this;
    }

    /**
     * Policy for handling a social sign-in whose email matches an existing user
     * that has not previously linked this provider. See
     * {@see AuthenticationSettings::$social_email_linking}.
     */
    public function socialEmailLinking(string $policy): static
    {
        $normalized = strtolower($policy);

        if (! in_array($normalized, ['require_login', 'trust_verified', 'auto'], true)) {
            throw new \InvalidArgumentException(
                "Unknown social_email_linking policy [{$policy}]. Expected one of: require_login, trust_verified, auto."
            );
        }

        $this->overrides['social_email_linking'] = $normalized;

        return $this;
    }

    /**
     * Whether the plugin should trust a provider-asserted `email_verified`
     * flag when stamping `users.email_verified_at` at social signup.
     */
    public function socialTrustVerifiedEmail(bool $trust = true): static
    {
        $this->overrides['social_trust_verified_email'] = $trust;

        return $this;
    }

    public function disposableEmailBlocking(bool $enabled = true): static
    {
        $this->overrides['disposable_email_blocking'] = $enabled;

        return $this;
    }

    /**
     * Restrict self-registration to these email domains (e.g. 'acme.com').
     * Empty list = any domain allowed. A leading `@` is tolerated. An entry
     * matches its exact host and any subdomain.
     *
     * @param  array<int, string>  $domains
     */
    public function allowedEmailDomains(array $domains): static
    {
        $this->overrides['allowed_email_domains'] = array_values(array_filter(array_unique(array_map(
            static fn (string $domain): string => ltrim(strtolower(trim($domain)), '@'),
            $domains,
        ))));

        return $this;
    }

    public function throttle(int $perMinute = 5, int $perDay = 50): static
    {
        $this->overrides['throttle_per_minute'] = $perMinute;
        $this->overrides['throttle_per_day'] = $perDay;

        return $this;
    }

    public function defaultCountryCode(string $code): static
    {
        $this->overrides['default_country_code'] = $code;

        return $this;
    }

    /**
     * Mount the same Livewire components as Filament panel pages, replacing
     * the built-in `->login()` / `->registration()` chrome. Off by default.
     *
     * @deprecated Use {@see FilamentPanelBasePlugin::withFilamentAuthPages()}
     *             instead. The top-level method does not require calling
     *             withAuthentication() in the panel provider, so it works
     *             cleanly when the global auth config lives in AppServiceProvider
     *             and the panel provider only needs to wire the in-panel UI.
     */
    public function filamentPanelPages(bool $login = false, bool $register = false): static
    {
        $this->modulePagesEnabled = $login || $register;
        $this->modulePagesLogin = $login;
        $this->modulePagesRegister = $register;

        return $this;
    }

    /**
     * Mark the plugin as active. Called by FilamentPanelBasePlugin::withAuthentication.
     */
    public function enable(): static
    {
        $this->enabled = true;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function hasFilamentPanelPages(): bool
    {
        return $this->modulePagesEnabled;
    }

    public function hasFilamentLoginPage(): bool
    {
        return $this->modulePagesLogin;
    }

    public function hasFilamentRegisterPage(): bool
    {
        return $this->modulePagesRegister;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    /**
     * Apply collected overrides to the AuthenticationSettings singleton.
     * Mutations are in-memory only — never persisted to the database.
     */
    public function apply(?AuthenticationSettings $settings = null): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $settings ??= app(AuthenticationSettings::class);

            foreach ($this->overrides as $key => $value) {
                if (property_exists($settings, $key)) {
                    $settings->{$key} = $value;
                }
            }
        } catch (\Throwable) {
            // settings unavailable (fresh install / no DB / pre-migration test boot) — silent no-op
        }
    }
}
