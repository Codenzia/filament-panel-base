<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor;

use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;

/**
 * Fluent configurator for the Two-Factor Authentication module. Reached via
 * `FilamentPanelBasePlugin::make()->withTwoFactor(fn ($tf) => $tf->...)`.
 *
 * Fluent values override TwoFactorSettings for the request lifecycle —
 * mutations are in-memory only, never persisted. Admins flip persistent
 * defaults through the Settings UI.
 *
 * @see FilamentPanelBasePlugin::withTwoFactor()
 */
class TwoFactorPlugin
{
    private bool $enabled = false;

    /** @var array<string, mixed> */
    private array $overrides = [];

    public function issuer(?string $name): static
    {
        $this->overrides['issuer'] = $name;

        return $this;
    }

    public function recoveryCodeCount(int $count): static
    {
        $this->overrides['recovery_code_count'] = max(1, $count);

        return $this;
    }

    public function digits(int $digits): static
    {
        $this->overrides['digits'] = in_array($digits, [6, 7, 8], true) ? $digits : 6;

        return $this;
    }

    public function period(int $seconds): static
    {
        $this->overrides['period'] = max(15, min(120, $seconds));

        return $this;
    }

    public function acceptanceWindow(int $window): static
    {
        $this->overrides['window'] = max(0, min(5, $window));

        return $this;
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function requireForRoles(array $roles): static
    {
        $this->overrides['require_for_roles'] = array_values(array_unique(array_filter($roles)));

        return $this;
    }

    public function rememberDevice(bool $enabled = true, ?int $days = null): static
    {
        $this->overrides['remember_device'] = $enabled;

        if ($days !== null) {
            $this->overrides['remember_device_days'] = max(1, $days);
        }

        return $this;
    }

    public function enable(): static
    {
        $this->enabled = true;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    /**
     * Apply collected overrides to the TwoFactorSettings singleton.
     * Settings unavailable (fresh install / pre-migration) -> silent no-op.
     */
    public function apply(?TwoFactorSettings $settings = null): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $settings ??= app(TwoFactorSettings::class);

            foreach ($this->overrides as $key => $value) {
                if (property_exists($settings, $key)) {
                    $settings->{$key} = $value;
                }
            }
        } catch (\Throwable) {
            // No DB / no migration — leave defaults in place.
        }
    }
}
