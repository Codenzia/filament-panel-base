<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth;

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;

/**
 * Fluent configurator for the Auth module. Reached via
 * `FilamentPanelBasePlugin::make()->withAuthentication(fn ($auth) => $auth->...)`.
 *
 * Fluent values are applied to the AuthenticationSettings singleton at the
 * time `apply()` is called, taking precedence over DB-stored values for the
 * remainder of the request. They never write back to the database — admins
 * who want persistent changes use the Settings UI.
 *
 * @see \Codenzia\FilamentPanelBase\FilamentPanelBasePlugin::withAuthentication()
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

    public function disposableEmailBlocking(bool $enabled = true): static
    {
        $this->overrides['disposable_email_blocking'] = $enabled;

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
        } catch (\Throwable) {
            return; // settings unavailable (fresh install / no DB) — silent no-op
        }

        foreach ($this->overrides as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->{$key} = $value;
            }
        }
    }
}
