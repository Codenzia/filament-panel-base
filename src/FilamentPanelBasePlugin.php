<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Filament panel plugin that provides shared multi-panel configuration.
 */
class FilamentPanelBasePlugin implements Plugin
{
    protected ?string $settingsClass = null;

    protected ?\Closure $settingsResolver = null;

    public function getId(): string
    {
        return 'filament-panel-base';
    }

    /**
     * Set a settings class for branding configuration.
     * The class should have: app_name, primary_color, secondary_color, etc.
     */
    public function settingsClass(?string $class): static
    {
        $this->settingsClass = $class;

        return $this;
    }

    /**
     * Set a closure that resolves the settings instance.
     */
    public function settingsUsing(?\Closure $resolver): static
    {
        $this->settingsResolver = $resolver;

        return $this;
    }

    /**
     * Resolve the settings instance.
     */
    public function resolveSettings(): ?object
    {
        if ($this->settingsResolver) {
            return ($this->settingsResolver)();
        }

        $class = $this->settingsClass ?? config('filament-panel-base.settings_class');

        if ($class && class_exists($class)) {
            return app($class);
        }

        return null;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
