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
use Filament\Support\Facades\FilamentIcon;
use Filament\View\PanelsIconAlias;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Auth;

abstract class BasePanelProvider extends PanelProvider
{
    use HasProfileSlideOver;

    protected bool $languageDropdownEnabled = true;

    /** @var array{label: string, icon: ?string, color: string}|null */
    protected ?array $titleBadgeConfig = null;

    protected bool $visitWebsiteEnabled = true;

    protected ?string $visitWebsiteLabel = null;

    protected string $sidebarCollapseButtonPosition = 'left';

    protected ?string $sidebarIcon = null;

    protected bool $sidebarSlideoverEnabled = true;

    protected bool $sidebarCollapseToIcons = true;

    protected bool $sidebarSearchEnabled = true;

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
    public function addTitleBadge(string $label, ?string $icon = null, string $color = 'primary', bool $showOnAuthForm = true): static
    {
        $this->titleBadgeConfig = [
            'label' => $label,
            'icon' => $icon,
            'color' => $color,
            'auth_visible' => $showOnAuthForm,
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
     * Set the sidebar collapse button position ('left' or 'right').
     */
    public function sidebarCollapseButtonPosition(string $position): static
    {
        $this->sidebarCollapseButtonPosition = $position;

        return $this;
    }

    /**
     * Set a custom icon for the sidebar collapse/expand button.
     * Accepts any Filament icon string (e.g. 'heroicon-o-bars-3').
     * Defaults to the built-in chevron SVG when not set.
     */
    public function sidebarIcon(string $icon): static
    {
        $this->sidebarIcon = $icon;

        return $this;
    }

    /**
     * Make the sidebar act as a slide-over overlay on desktop.
     *
     * Normally, Filament's sidebar is sticky and pushes the main content to the right.
     * Enabling this keeps the sidebar fixed (overlay) on all screen sizes and shows
     * the dim backdrop on desktop, matching mobile behaviour.
     */
    public function sidebarSlideover(bool $enabled = true): static
    {
        $this->sidebarSlideoverEnabled = $enabled;

        return $this;
    }

    /**
     * When sidebar slideover is enabled, collapse to an icon-only narrow bar
     * instead of fully hiding the sidebar off-screen.
     *
     * Default behaviour (false): sidebar slides fully off-screen when closed.
     * When enabled (true): the sidebar shrinks to Filament's icon-only width,
     * letting users still see and click nav item icons without opening the drawer.
     */
    public function sidebarCollapseToIcons(bool $enabled = true): static
    {
        $this->sidebarCollapseToIcons = $enabled;

        return $this;
    }

    /**
     * Show a search box at the top of the sidebar navigation.
     *
     * When enabled, a search input is injected via the SIDEBAR_NAV_START render hook.
     * Typing filters navigation items client-side (Alpine.js) by matching the item label.
     * Groups with no matching items are hidden automatically.
     */
    public function sidebarSearchable(bool $enabled = true): static
    {
        $this->sidebarSearchEnabled = $enabled;

        return $this;
    }

    /**
     * Apply shared configuration (branding, colors, user menu, render hooks) to a panel.
     */
    protected function configureSharedSettings(Panel $panel): Panel
    {
        $panel
            ->brandName(fn (): string => $this->resolveBrandName())
            ->brandLogo(fn (): ?string => $this->resolveBrandLogo())
            ->brandLogoHeight('2.5rem')
            ->favicon(fn (): ?string => $this->resolveFavicon())
            ->colors(fn (): array => $this->getColorsFromSettings())
            ->userMenuItems($this->getUserMenuItems($panel))
            ->sidebarCollapsibleOnDesktop();

        if ($this->titleBadgeConfig) {
            $showOnAuthForms = $this->titleBadgeConfig['auth_visible'] ?? false;
            $panel->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): \Illuminate\Contracts\View\View => $this->getPanelBadge(),
            );
            if ($showOnAuthForms) {
                $panel->renderHook(
                    PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                    fn (): \Illuminate\Contracts\View\View => $this->getPanelBadge(centered: true),
                );
                $panel->renderHook(
                    PanelsRenderHook::AUTH_REGISTER_FORM_BEFORE,
                    fn (): \Illuminate\Contracts\View\View => $this->getPanelBadge(centered: true),
                );
            }
        }

        if ($this->visitWebsiteEnabled) {
            $panel->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): \Illuminate\Contracts\View\View => $this->getVisitWebsiteButton(),
            );
        }

        if ($this->languageDropdownEnabled) {
            $panel->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => $this->getLocaleToggle(),
            );
        }

        if ($this->sidebarCollapseButtonPosition === 'right') {
            $this->registerRightSidebarCollapseButton($panel);
        } elseif ($this->sidebarIcon !== null || $this->sidebarSlideoverEnabled) {
            // For the default left-side Filament buttons, override their icon aliases.
            // When slideover is on and no explicit icon is set, borrow bars-3 (the mobile drawer icon).
            $leftIcon = $this->sidebarIcon ?? ($this->sidebarSlideoverEnabled ? 'heroicon-o-bars-3' : null);

            if ($leftIcon !== null) {
                FilamentIcon::register([
                    PanelsIconAlias::SIDEBAR_EXPAND_BUTTON => $leftIcon,
                    PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => $leftIcon,
                ]);
            }
        }

        if ($this->sidebarSlideoverEnabled) {
            $this->registerSidebarSlideover($panel);
        }

        if ($this->sidebarSearchEnabled) {
            $panel->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): \Illuminate\Contracts\View\View => view('panel-base::components.sidebar-search'),
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
            'profile' => fn (Action $action) => $action
                ->url(null)
                ->label(fn (): string => filament()->getUserName(filament()->auth()->user())),

            // Profile edit slideOver
            $this->getProfileSlideOverAction(),
            Action::make('um_role')
                ->disabled()
                ->icon('heroicon-c-book-open')
                ->label(fn () => method_exists(filament()->auth()->user(), 'roles')
                    ? filament()->auth()->user()?->roles->pluck('name')->join(', ') ?? __('User')
                    : __('User')),
            Action::make('um_phone')
                ->disabled()
                ->icon('heroicon-o-phone')
                ->label(fn () => filament()->auth()->user()?->phone ?? __('No phone')),
            Action::make('um_email')
                ->disabled()
                ->icon('heroicon-o-envelope')
                ->label(fn () => filament()->auth()->user()?->email ?? __('No Email')),
        ];

        // Cross-panel navigation
        if (count($panels) >= 2) {
            $dashboardPanel = $panels[1] ?? 'dashboard';
            $adminPanel = $panels[0] ?? 'admin';

            if ($panelId === $dashboardPanel) {
                $items[] = Action::make('admin-panel')
                    ->label(__('Admin Panel'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url('/'.$adminPanel)
                    ->color('info')
                    ->visible(fn (): bool => Auth::user()?->canAccessPanel(filament()->getPanel($adminPanel)) ?? false)
                    ->sort(50);
            } elseif ($panelId === $adminPanel) {
                $items[] = Action::make('user-dashboard')
                    ->label(__('My Dashboard'))
                    ->icon('heroicon-o-squares-2x2')
                    ->url('/'.$dashboardPanel)
                    ->color('primary')
                    ->visible(fn (): bool => Auth::user()?->canAccessPanel(filament()->getPanel($dashboardPanel)) ?? false)
                    ->sort(50);
            }
        }

        return $items;
    }

    /**
     * Get a small badge identifying the current panel.
     */
    protected function getPanelBadge(bool $centered = false): \Illuminate\Contracts\View\View
    {
        return view('panel-base::components.panel-badge', [
            'label' => __($this->titleBadgeConfig['label']),
            'color' => $this->titleBadgeConfig['color'] ?? 'primary',
            'icon' => $this->titleBadgeConfig['icon'] ?? null,
            'centered' => $centered,
        ]);
    }

    protected function getVisitWebsiteButton(): \Illuminate\Contracts\View\View
    {
        return view('panel-base::components.visit-website-button', [
            'label' => $this->visitWebsiteLabel ?? __('Visit Website'),
        ]);
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

    /**
     * Register a custom sidebar collapse button on the right side.
     *
     * Uses two render hooks:
     *  - TOPBAR_LOGO_BEFORE: injects a sibling of .fi-topbar-collapse-sidebar-btn-ctn
     *    that removes it via a MutationObserver scoped to .fi-topbar-start.
     *  - SIDEBAR_NAV_START: injects the custom pill button at the top of the nav,
     *    above all nav items, so it sits at the top edge of the visible sidebar.
     */
    protected function registerRightSidebarCollapseButton(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::TOPBAR_LOGO_BEFORE,
            fn (): \Illuminate\Contracts\View\View => view('panel-base::components.sidebar-collapse-remover'),
        );

        $panel->renderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            fn (): \Illuminate\Contracts\View\View => view('panel-base::components.sidebar-collapse-button', [
                'sidebarIcon' => $this->sidebarIcon,
            ]),
        );
    }

    /**
     * Override Filament's desktop sidebar layout so the sidebar overlays content
     * (slide-over) instead of pushing it, with a suite of polish animations.
     *
     * CSS rules injected into <head>:
     *  1. Sidebar slide — desktop-only transform transition + translateX(-100%) when
     *     closed. Scoped to lg to avoid conflicting with Filament's mobile transitions.
     *  2. Sidebar open state — fixed position, z-index, background, box-shadow.
     *  3. Topbar raised to z-35 so its buttons stay above the backdrop (z-30).
     *  4. Frosted glass overlay — backdrop-filter: blur on the dim backdrop.
     *  5. Content scale-down — main content shrinks + blurs when the drawer opens.
     *  6. Nav items stagger — each top-level nav item cascades in with a slight delay.
     */
    protected function registerSidebarSlideover(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::HEAD_END,
            fn (): \Illuminate\Contracts\View\View => view('panel-base::components.sidebar-slideover-styles', [
                'collapseToIcons' => $this->sidebarCollapseToIcons,
            ]),
        );
    }
}
