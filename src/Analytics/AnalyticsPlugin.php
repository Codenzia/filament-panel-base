<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics;

use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;
use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
use InvalidArgumentException;

/**
 * Fluent configurator for the Analytics module. Reached via
 * `FilamentPanelBasePlugin::make()->withAnalytics(fn ($a) => $a->...)`.
 *
 * Fluent values override AnalyticsSettings for the request lifecycle —
 * mutations are in-memory only, never persisted. Admins flip persistent
 * defaults through the Settings UI.
 *
 * @see FilamentPanelBasePlugin::withAnalytics()
 */
class AnalyticsPlugin
{
    private bool $enabled = false;

    /** @var array<string, mixed> */
    private array $overrides = [];

    public function trackVisits(bool $enabled = true): static
    {
        $this->overrides['track_visits'] = $enabled;

        return $this;
    }

    public function trackAuthEvents(bool $enabled = true): static
    {
        $this->overrides['track_auth_events'] = $enabled;

        return $this;
    }

    public function trackResourceUsage(bool $enabled = true): static
    {
        $this->overrides['track_resource_usage'] = $enabled;

        return $this;
    }

    /**
     * One of: 'none', 'truncate', 'hash'. See AnalyticsSettings docs.
     */
    public function ipAnonymization(string $mode): static
    {
        $normalized = strtolower($mode);

        if (! in_array($normalized, ['none', 'truncate', 'hash'], true)) {
            throw new InvalidArgumentException(
                "Unknown ip_anonymization mode [{$mode}]. Expected one of: none, truncate, hash."
            );
        }

        $this->overrides['ip_anonymization'] = $normalized;

        return $this;
    }

    public function retainRawDays(int $days): static
    {
        $this->overrides['retain_raw_days'] = max(1, $days);

        return $this;
    }

    public function retainAggregatedDays(int $days): static
    {
        $this->overrides['retain_aggregated_days'] = max(1, $days);

        return $this;
    }

    public function botFilter(bool $enabled = true): static
    {
        $this->overrides['bot_filter'] = $enabled;

        return $this;
    }

    /**
     * Queue connection name for RecordVisitJob. Pass null to write
     * synchronously (test/dev — never use sync in production hot paths).
     */
    public function writeQueue(?string $queue): static
    {
        $this->overrides['write_queue'] = $queue === '' ? null : $queue;

        return $this;
    }

    /**
     * @param  array<int, string>  $widgets
     */
    public function enabledWidgets(array $widgets): static
    {
        $this->overrides['enabled_widgets'] = array_values(array_unique($widgets));

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
     * Apply collected overrides to the AnalyticsSettings singleton.
     * Settings unavailable (fresh install / pre-migration) → silent no-op.
     */
    public function apply(?AnalyticsSettings $settings = null): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $settings ??= app(AnalyticsSettings::class);

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
