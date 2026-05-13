<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase;

use Codenzia\FilamentPanelBase\Auth\AuthenticationPlugin;
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

    protected ?AuthenticationPlugin $authentication = null;

    protected bool $filamentAuthLoginEnabled = false;

    protected bool $filamentAuthRegisterEnabled = false;

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
     * After enabling, run: php artisan filament-panel-base:enable-translations
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
     * Configure the Auth module — signup, login, verification, social,
     * moderation. The closure receives an {@see AuthenticationPlugin}
     * instance for fluent configuration; values applied via the fluent API
     * override AuthenticationSettings for the request lifecycle.
     *
     * Example:
     *
     *   FilamentPanelBasePlugin::make()
     *       ->withAuthentication(fn ($auth) => $auth
     *           ->credentials('email', 'phone')
     *           ->moderation()
     *           ->verification(driver: 'whatsapp', allowed: ['whatsapp','email'])
     *       );
     */
    public function withAuthentication(\Closure $callback): static
    {
        $this->authentication = app(AuthenticationPlugin::class);
        $callback($this->authentication);
        $this->authentication->enable()->apply();

        return $this;
    }

    /**
     * Resolve the AuthenticationPlugin instance (or null when the host
     * never called `->withAuthentication()`).
     */
    public function getAuthentication(): ?AuthenticationPlugin
    {
        return $this->authentication;
    }

    /**
     * Whether the Auth module is active for this panel.
     */
    public function isAuthenticationEnabled(): bool
    {
        return $this->authentication?->isEnabled() ?? false;
    }

    /**
     * Mount the auth module's Livewire register/login components inside
     * Filament's panel chrome (replaces the panel's built-in `->login()` /
     * `->registration()` pages). This is a panel-level concern only — it
     * does NOT touch AuthenticationSettings, so it's safe to call during
     * panel boot regardless of migration state.
     *
     * Typically called alongside `withAuthentication()` in AppServiceProvider:
     *
     *   // AppServiceProvider::boot — global auth config
     *   FilamentPanelBasePlugin::make()
     *       ->withAuthentication(fn ($auth) => $auth->credentials(...)->moderation());
     *
     *   // UserPanelProvider::panel — per-panel UI adapter
     *   ->plugin(FilamentPanelBasePlugin::make()->withFilamentAuthPages(login: true))
     */
    public function withFilamentAuthPages(bool $login = false, bool $register = false): static
    {
        $this->filamentAuthLoginEnabled = $login;
        $this->filamentAuthRegisterEnabled = $register;

        return $this;
    }

    public function hasFilamentAuthLogin(): bool
    {
        return $this->filamentAuthLoginEnabled;
    }

    public function hasFilamentAuthRegister(): bool
    {
        return $this->filamentAuthRegisterEnabled;
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
        if ($this->translationsEnabled) {
            $panel->resources([
                \Codenzia\FilamentPanelBase\Filament\Resources\TranslationResource::class,
            ]);
        }

        // Honour both the top-level fluent API (preferred) and the legacy
        // AuthenticationPlugin::filamentPanelPages() route (deprecated).
        $loginEnabled = $this->filamentAuthLoginEnabled
            || ($this->authentication?->hasFilamentLoginPage() ?? false);

        $registerEnabled = $this->filamentAuthRegisterEnabled
            || ($this->authentication?->hasFilamentRegisterPage() ?? false);

        if ($loginEnabled) {
            $panel->login(\Codenzia\FilamentPanelBase\Auth\Filament\Pages\Login::class);
        }

        if ($registerEnabled) {
            $panel->registration(\Codenzia\FilamentPanelBase\Auth\Filament\Pages\Register::class);
        }
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
