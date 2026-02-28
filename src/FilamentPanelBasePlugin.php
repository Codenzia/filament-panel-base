<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase;

use Codenzia\FilamentPanelBase\Contracts\ProvidesThemeColors;
use Codenzia\FilamentPanelBase\Support\ThemePresets;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Filament panel plugin that provides shared multi-panel configuration.
 */
class FilamentPanelBasePlugin implements Plugin
{
    protected ?string $settingsClass = null;

    protected ?\Closure $settingsResolver = null;

    protected bool $translationsEnabled = false;

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
     * Enable the built-in Translation Manager resource for this panel.
     *
     * After enabling, run: php artisan panel-base:enable-translations
     * to publish the required migrations and config.
     */
    public function withTranslations(bool $enabled = true): static
    {
        $this->translationsEnabled = $enabled;

        return $this;
    }

    /**
     * Whether the Translation Manager has been activated for this panel.
     */
    public function isTranslationsEnabled(): bool
    {
        return $this->translationsEnabled;
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

    /**
     * Get the resolved theme colors for frontend CSS variable injection.
     *
     * Resolution order:
     * 1. Settings class implementing ProvidesThemeColors
     * 2. Config preset + color overrides
     * 3. Ocean Blue defaults
     *
     * @return array<string, string>
     */
    public function getThemeColors(): array
    {
        $settings = $this->resolveSettings();

        if ($settings instanceof ProvidesThemeColors) {
            return $settings->getThemeColors();
        }

        // Fall back to config-based preset
        $preset = config('filament-panel-base.theme.preset', 'ocean_blue');
        $colors = ThemePresets::get($preset) ?? ThemePresets::defaults();
        unset($colors['label']);

        // Merge any config color overrides
        $overrides = config('filament-panel-base.theme.colors', []);

        return array_merge($colors, array_filter($overrides));
    }

    public function register(Panel $panel): void
    {
        if (! $this->translationsEnabled) {
            return;
        }

        $panel->resources([
            \Codenzia\FilamentPanelBase\Filament\Resources\TranslationResource::class,
        ]);
    }

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
