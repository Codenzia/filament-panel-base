<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette;

use Codenzia\FilamentPanelBase\CommandPalette\Settings\CommandPaletteSettings;
use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;

/**
 * Fluent configurator for the Command Palette module. Reached via
 * `FilamentPanelBasePlugin::make()->withCommandPalette(fn ($c) => $c->...)`.
 *
 * Fluent values override CommandPaletteSettings for the request lifecycle —
 * mutations are in-memory only, never persisted. Admins flip persistent
 * defaults through the Settings UI.
 *
 * @see FilamentPanelBasePlugin::withCommandPalette()
 */
class CommandPalettePlugin
{
    private bool $enabled = false;

    /** @var array<string, mixed> */
    private array $overrides = [];

    public function trackRecentViews(bool $enabled = true): static
    {
        $this->overrides['track_recent_views'] = $enabled;

        return $this;
    }

    public function recentViewLimit(int $limit): static
    {
        $this->overrides['recent_view_limit'] = max(1, min(50, $limit));

        return $this;
    }

    public function hotkeyLabel(string $label): static
    {
        $this->overrides['hotkey_label'] = $label;

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

    public function apply(?CommandPaletteSettings $settings = null): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $settings ??= app(CommandPaletteSettings::class);

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
