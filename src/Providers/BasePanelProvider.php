<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Providers;

use Codenzia\FilamentPanelBase\Concerns\HasProfileSlideOver;
use Codenzia\FilamentPanelBase\Contracts\ProvidesThemeColors;
use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
use Codenzia\FilamentPanelBase\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

abstract class BasePanelProvider extends PanelProvider
{
    use HasProfileSlideOver;

    protected bool $languageDropdownEnabled = true;

    /** @var array{label: string, icon: ?string, color: string}|null */
    protected ?array $titleBadgeConfig = null;

    protected bool $visitWebsiteEnabled = true;

    protected ?string $visitWebsiteLabel = null;

    /**
     * Enable or disable the language dropdown in the topbar.
     */
    public function showLanguageDropdown(bool $show = true): static
    {
        $this->languageDropdownEnabled = $show;

        return $this;
    }

    /**
     * Add a title badge next to the logo in the topbar.
     */
    public function addTitleBadge(string $label, ?string $icon = null, string $color = 'primary'): static
    {
        $this->titleBadgeConfig = [
            'label' => $label,
            'icon' => $icon,
            'color' => $color,
        ];

        return $this;
    }

    /**
     * Enable or disable the "Visit Website" button in the topbar.
     */
    public function showVisitWebsite(bool $show = true, ?string $label = null): static
    {
        $this->visitWebsiteEnabled = $show;
        $this->visitWebsiteLabel = $label;

        return $this;
    }

    /**
     * Apply shared configuration (branding, colors, user menu, render hooks) to a panel.
     */
    protected function configureSharedSettings(Panel $panel): Panel
    {
        $panel
            ->brandName(fn(): string => $this->resolveBrandName())
            ->brandLogo(fn(): ?string => $this->resolveBrandLogo())
            ->brandLogoHeight('2.5rem')
            ->favicon(fn(): ?string => $this->resolveFavicon())
            ->colors(fn(): array => $this->getColorsFromSettings())
            ->userMenuItems($this->getUserMenuItems($panel))
            ->sidebarCollapsibleOnDesktop();

        if ($this->titleBadgeConfig) {
            $panel->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn(): HtmlString => $this->getPanelBadge(),
            );
        }

        if ($this->visitWebsiteEnabled) {
            $panel->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): HtmlString => $this->getVisitWebsiteButton(),
            );
        }

        if ($this->languageDropdownEnabled) {
            $panel->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn(): string => $this->getLocaleToggle(),
            );
        }

        $this->registerTranslatablePlugin($panel);

        return $panel;
    }

    /**
     * Auto-register the SpatieTranslatablePlugin when `lara-zeus/spatie-translatable` is installed.
     *
     * Uses locales from `config('filament-panel-base.locale.available')`.
     * Override this method to customise or disable the integration.
     */
    protected function registerTranslatablePlugin(Panel $panel): void
    {
        if (! class_exists(\LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin::class)) {
            return;
        }

        $locales = config('filament-panel-base.locale.available', ['en']);

        $panel->plugin(
            \LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin::make()
                ->defaultLocales($locales)
                ->persist()
        );
    }

    /**
     * Resolve the brand name from settings or config.
     */
    protected function resolveBrandName(): string
    {
        $settings = FilamentPanelBasePlugin::make()->resolveSettings();

        if ($settings && property_exists($settings, 'app_name')) {
            return $settings->app_name;
        }

        return config('app.name', 'Laravel');
    }

    /**
     * Resolve the brand logo URL from settings.
     */
    protected function resolveBrandLogo(): ?string
    {
        $settings = FilamentPanelBasePlugin::make()->resolveSettings();

        if ($settings && method_exists($settings, 'getAppLogoUrl')) {
            return $settings->getAppLogoUrl();
        }

        return null;
    }

    /**
     * Resolve the favicon URL from settings.
     */
    protected function resolveFavicon(): ?string
    {
        $settings = FilamentPanelBasePlugin::make()->resolveSettings();

        if ($settings && method_exists($settings, 'getAppFaviconUrl')) {
            return $settings->getAppFaviconUrl();
        }

        return null;
    }

    protected function getColorsFromSettings(): array
    {
        $settings = FilamentPanelBasePlugin::make()->resolveSettings();

        if ($settings) {
            return $this->getColorsFromSettingsInstance($settings);
        }

        return $this->getColorsFromConfig();
    }

    /**
     * Build color array from a settings instance that has color properties.
     *
     * When the settings class implements ProvidesThemeColors, the full theme
     * palette is used. Otherwise, falls back to reading individual color properties.
     */
    protected function getColorsFromSettingsInstance(object $settings): array
    {
        // When settings implements ProvidesThemeColors, use the full theme palette
        if ($settings instanceof ProvidesThemeColors) {
            $themeColors = $settings->getThemeColors();
            $colors = [];

            $filamentMap = [
                'primary' => 'primary_color',
                'secondary' => 'secondary_color',
                'danger' => 'danger_color',
                'warning' => 'warning_color',
                'success' => 'success_color',
                'info' => 'info_color',
            ];

            foreach ($filamentMap as $filamentName => $themeKey) {
                if (! empty($themeColors[$themeKey])) {
                    $colors[$filamentName] = Color::hex($themeColors[$themeKey]);
                }
            }

            $colors['gray'] = Color::Slate;

            return $colors;
        }

        // Legacy: settings with individual color properties
        $colors = [];
        $colorNames = ['primary', 'secondary', 'danger', 'warning', 'success', 'info'];

        foreach ($colorNames as $name) {
            $property = "{$name}_color";
            if (property_exists($settings, $property) && $settings->{$property}) {
                $colors[$name] = Color::hex($settings->{$property});
            }
        }

        $colors['gray'] = Color::Slate;

        return $colors;
    }

    /**
     * Build color array from config values.
     */
    protected function getColorsFromConfig(): array
    {
        $configColors = config('filament-panel-base.colors', []);
        $colors = [];

        foreach ($configColors as $name => $hex) {
            $colors[$name] = Color::hex($hex);
        }

        $colors['gray'] = Color::Slate;

        return $colors;
    }

    /**
     * Build the user menu items shared across all panels.
     * Includes: user name label, profile slideOver, cross-panel navigation links.
     */
    protected function getUserMenuItems(Panel $panel): array
    {
        $panelId = $panel->getId();
        $panels = config('filament-panel-base.panels', ['admin', 'dashboard']);

        $items = [
            // Show user name as the top item (non-clickable label)
            'profile' => fn(Action $action) => $action
                ->url(null)
                ->label(fn(): string => filament()->getUserName(filament()->auth()->user())),

            // Profile edit slideOver
            $this->getProfileSlideOverAction(),
            Action::make('um_role')
                ->disabled()
                ->icon('heroicon-c-book-open')
                ->label(fn() => method_exists(filament()->auth()->user(), 'roles')
                    ? filament()->auth()->user()?->roles->pluck('name')->join(', ') ?? __('User')
                    : __('User')),
            Action::make('um_phone')
                ->disabled()
                ->icon('heroicon-o-phone')
                ->label(fn() => filament()->auth()->user()?->phone ?? __('No phone')),
            Action::make('um_email')
                ->disabled()
                ->icon('heroicon-o-envelope')
                ->label(fn() => filament()->auth()->user()?->email ?? __('No Email')),
        ];

        // Cross-panel navigation
        if (count($panels) >= 2) {
            $dashboardPanel = $panels[1] ?? 'dashboard';
            $adminPanel = $panels[0] ?? 'admin';

            if ($panelId === $dashboardPanel) {
                $items[] = Action::make('admin-panel')
                    ->label(__('Admin Panel'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url('/' . $adminPanel)
                    ->color('info')
                    ->visible(fn(): bool => Auth::user()?->canAccessPanel(filament()->getPanel($adminPanel)) ?? false)
                    ->sort(50);
            } elseif ($panelId === $adminPanel) {
                $items[] = Action::make('user-dashboard')
                    ->label(__('My Dashboard'))
                    ->icon('heroicon-o-squares-2x2')
                    ->url('/' . $dashboardPanel)
                    ->color('primary')
                    ->visible(fn(): bool => Auth::user()?->canAccessPanel(filament()->getPanel($dashboardPanel)) ?? false)
                    ->sort(50);
            }
        }

        return $items;
    }

    /**
     * Get a small badge identifying the current panel.
     */
    protected function getPanelBadge(): HtmlString
    {
        $label = e(__($this->titleBadgeConfig['label']));
        $color = $this->titleBadgeConfig['color'] ?? 'primary';
        $icon = $this->titleBadgeConfig['icon'] ?? '';

        $colorClasses = [
            'primary' => 'bg-primary-100 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/30',
            'success' => 'bg-success-100 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30',
            'warning' => 'bg-warning-100 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-400/30',
            'danger'  => 'bg-danger-100 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/30',
            'info'    => 'bg-info-100 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400 dark:ring-info-400/30',
            'gray'    => 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-400/30',
        ];

        $classes = $colorClasses[$color] ?? $colorClasses['primary'];
        $iconHtml = $icon ? svg($icon, 'w-5 h-5 mx-1.5')->toHtml() : '';

        return new HtmlString(
            '<span style="margin-left: 1rem; margin-right: 1rem;" class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset ' . $classes . '">' . $iconHtml . $label . '</span>'
        );
    }

    protected function getVisitWebsiteButton(): HtmlString
    {
        $label = $this->visitWebsiteLabel ?? __('Visit Website');

        return new HtmlString('
            <a href="/" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/10" title="' . $label . '">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
                <span class="hidden sm:inline">' . $label . '</span>
            </a>
        ');
    }

    /**
     * Render the locale switcher dropdown for the topbar.
     */
    protected function getLocaleToggle(): string
    {
        return view('panel-base::components.locale-switcher', [
            'locales' => SetLocale::getLocales(),
            'currentLocale' => app()->getLocale(),
        ])->render();
    }

    /**
     * Get the shared middleware stack used by all panels.
     */
    protected function getSharedMiddleware(): array
    {
        return [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Filament\Http\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Filament\Http\Middleware\DisableBladeIconComponents::class,
            \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
            SetLocale::class,
        ];
    }
}
