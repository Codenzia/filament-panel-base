<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sessions;

use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
use Codenzia\FilamentPanelBase\Sessions\Settings\SessionManagementSettings;

/**
 * Fluent configurator for the Session & Device Management module. Reached via
 * `FilamentPanelBasePlugin::make()->withSessionManagement(fn ($s) => $s->...)`.
 *
 * Fluent values override SessionManagementSettings for the request lifecycle —
 * mutations are in-memory only, never persisted. Admins flip persistent
 * defaults through the Settings UI.
 *
 * @see FilamentPanelBasePlugin::withSessionManagement()
 */
class SessionManagementPlugin
{
    private bool $enabled = false;

    /** @var array<string, mixed> */
    private array $overrides = [];

    public function notifyOnNewDevice(bool $enabled = true): static
    {
        $this->overrides['notify_on_new_device'] = $enabled;

        return $this;
    }

    public function idleThresholdMinutes(int $minutes): static
    {
        $this->overrides['idle_threshold_minutes'] = max(1, $minutes);

        return $this;
    }

    public function allowLogoutOtherDevices(bool $enabled = true): static
    {
        $this->overrides['allow_logout_other_devices'] = $enabled;

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

    public function apply(?SessionManagementSettings $settings = null): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $settings ??= app(SessionManagementSettings::class);

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
