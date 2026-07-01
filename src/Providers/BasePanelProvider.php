<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Providers;

use Codenzia\FilamentPanelBase\Analytics\Http\Middleware\TrackVisit;
use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;
use Codenzia\FilamentPanelBase\Concerns\HasProfileSlideOver;
use Codenzia\FilamentPanelBase\Contracts\ProvidesLocales;
use Codenzia\FilamentPanelBase\Contracts\ProvidesThemeColors;
use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
use Codenzia\FilamentPanelBase\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentIcon;
use Filament\View\PanelsIconAlias;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;

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

    protected bool $darkModeToggleEnabled = false;

    protected ?string $authLinksLoginPanel = null;

    protected ?string $authLinksRegisterPanel = null;

    protected bool $sidebarCollapsibleEnabled = true;

    /**
     * Per-panel color overrides set via primaryColor() / brandColors().
     *
     * @var array<string, mixed>|null
     */
    protected ?array $brandColors = null;

    /**
     * Override the panel's color set (primary, danger, gray, …).
     *
     * The recommended, consistent way to brand a panel — replaces raw
     * `->colors([...])` calls in host providers. Values may be Filament color
     * constants (e.g. Color::Indigo), a hex via Color::hex('#...'), or a full
     * 50…950 shade array. Call this before configureSharedSettings(); the color
     * closure reads it at resolve time.
     *
     * Precedence (low → high): neutral default → config → settings model →
     * brandColors(). An explicit brandColors()/primaryColor() pin wins over a
     * ProvidesThemeColors settings model, so calling it opts that panel's
     * colors out of live theming; omit it to let the settings model drive.
     *
     * @param  array<string, mixed>  $colors
     */
    public function brandColors(array $colors): static
    {
        $this->brandColors = array_merge($this->brandColors ?? [], $colors);

        return $this;
    }

    /**
     * Convenience for the common case: override only the primary color.
     * Accepts a Filament color constant/array or a '#hex' string.
     */
    public function primaryColor(string|array $color): static
    {
        return $this->brandColors(['primary' => is_string($color) ? Color::hex($color) : $color]);
    }

    /**
     * Make the sidebar collapsible on desktop (default true — Filament's standard).
     *
     * Set to false for catalog/guest panels where the sidebar is the primary
     * navigation and should always be visible — avoids the chicken-and-egg
     * where the user can't find the toggle button when the sidebar is closed.
     */
    public function sidebarCollapsible(bool $enabled = true): static
    {
        $this->sidebarCollapsibleEnabled = $enabled;

        return $this;
    }

    /**
     * Enable the dark / light mode toggle in the topbar (default: off).
     *
     * Recommended for **guest / public panels** where there is no Filament
     * user menu — the user menu already includes a built-in theme toggle
     * for authenticated users, so leaving this off on auth-required panels
     * avoids two conflicting toggles in the same chrome.
     *
     * The toggle is purely client-side: Alpine.js + `localStorage.theme`,
     * paired with `<x-filament-panel-base::dark-mode-script />` for FOUC prevention.
     */
    public function showDarkModeToggle(bool $show = true): static
    {
        $this->darkModeToggleEnabled = $show;

        return $this;
    }

    /**
     * Show "Sign in" / "Sign up" buttons in the topbar (guest panels only).
     *
     * Pass the panel IDs that own the login + registration routes. The buttons
     * resolve their URLs via Filament::getPanel($id)->getLoginUrl() so they
     * always point to the right place even if the panel paths change.
     * The buttons render only when no user is authenticated.
     *
     * @param  string|null  $loginPanel  Panel ID providing login (e.g. 'user'); null disables.
     * @param  string|null  $registerPanel  Panel ID providing registration; null hides Sign up.
     */
    public function showAuthLinks(?string $loginPanel = null, ?string $registerPanel = null): static
    {
        $this->authLinksLoginPanel = $loginPanel;
        $this->authLinksRegisterPanel = $registerPanel;

        return $this;
    }

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
            ->userMenuItems($this->getUserMenuItems($panel));

        if ($this->sidebarCollapsibleEnabled) {
            $panel->sidebarCollapsibleOnDesktop();
        }

        if ($this->titleBadgeConfig) {
            $showOnAuthForms = $this->titleBadgeConfig['auth_visible'] ?? false;
            $panel->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): View => $this->getPanelBadge(),
            );
            if ($showOnAuthForms) {
                $panel->renderHook(
                    PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                    fn (): View => $this->getPanelBadge(centered: true),
                );
                $panel->renderHook(
                    PanelsRenderHook::AUTH_REGISTER_FORM_BEFORE,
                    fn (): View => $this->getPanelBadge(centered: true),
                );
            }
        }

        if ($this->visitWebsiteEnabled) {
            $panel->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): View => $this->getVisitWebsiteButton(),
            );
        }

        if ($this->languageDropdownEnabled) {
            $panel->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => $this->getLocaleToggle(),
            );
        }

        if ($this->darkModeToggleEnabled) {
            $panel->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): View => view('filament-panel-base::components.dark-mode-toggle'),
            );
        }

        if ($this->authLinksLoginPanel !== null) {
            $panel->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): View => view('filament-panel-base::components.auth-links', [
                    'loginPanel' => $this->authLinksLoginPanel,
                    'registerPanel' => $this->authLinksRegisterPanel,
                ]),
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

        // When slideover is OFF but the sidebar is still collapsible-with-icons,
        // inject the standalone polish CSS (with explicit narrow width). Apps using
        // slideover already get the icon-only polish from sidebar-slideover-styles
        // and shouldn't have it duplicated.
        if (! $this->sidebarSlideoverEnabled
            && $this->sidebarCollapsibleEnabled
            && $this->sidebarCollapseToIcons
        ) {
            $panel->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament-panel-base::components.sidebar-collapsed-icon-styles'),
            );
        }

        if ($this->sidebarSearchEnabled) {
            $panel->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): View => view('filament-panel-base::components.sidebar-search'),
            );
        }

        $this->registerTranslatablePlugin($panel);

        return $panel;
    }

    /**
     * Auto-register the SpatieTranslatablePlugin when `lara-zeus/spatie-translatable` is installed.
     *
     * Pulls locales dynamically from the ProvidesLocales provider (database),
     * falling back to `config('filament-panel-base.locale.available')`.
     * Override this method to customise or disable the integration.
     */
    protected function registerTranslatablePlugin(Panel $panel): void
    {
        if (! class_exists(SpatieTranslatablePlugin::class)) {
            return;
        }

        $providerClass = config('filament-panel-base.locale.provider');

        // Wrap in try-catch: on fresh deployments the database/cache tables
        // may not exist yet. Fall back to config-based locales gracefully
        // so the app can still boot (e.g. to run migrations via /console).
        try {
            if ($providerClass && class_exists($providerClass) && is_a($providerClass, ProvidesLocales::class, true)) {
                $locales = array_keys($providerClass::getActive());
            } else {
                $locales = config('filament-panel-base.locale.available', ['en']);
            }
        } catch (QueryException) {
            $locales = config('filament-panel-base.locale.available', ['en']);
        }

        $panel->plugin(
            SpatieTranslatablePlugin::make()
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

    /**
     * Resolve the panel's colors by layered precedence (low → high):
     *
     *   1. Neutral default (Color::Blue for primary) — so a panel that sets
     *      nothing is never Filament's scaffold amber.
     *   2. config('filament-panel-base.colors') — the app-wide default.
     *   3. Settings model (ProvidesThemeColors / legacy *_color props) — the
     *      admin-editable, live source; only the keys it actually provides.
     *   4. Per-panel brandColors() / primaryColor() declared in the provider —
     *      an explicit code pin, so it wins over everything (matching the old
     *      "raw ->colors() applied after configureSharedSettings" behaviour).
     *
     * Each layer overlays the previous per color key, so unset keys always fall
     * through to a sane value (never amber). A panel that wants live, admin-
     * editable theming simply omits brandColors() and lets the settings model
     * (3) drive; calling brandColors() opts that panel's colors out of live
     * theming. Gray is applied last via getGrayColor() unless brandColors()
     * (or config) provides its own.
     */
    protected function getColorsFromSettings(): array
    {
        $colors = $this->getDefaultColors();

        $colors = array_replace($colors, $this->getColorsFromConfig());

        $settings = FilamentPanelBasePlugin::make()->resolveSettings();

        if ($settings) {
            $colors = array_replace($colors, $this->getColorsFromSettingsInstance($settings));
        }

        if ($this->brandColors) {
            $colors = array_replace($colors, $this->brandColors);
        }

        $colors['gray'] ??= $this->getGrayColor();

        return $colors;
    }

    /**
     * The neutral fallback palette used when nothing else sets a color.
     * Deliberately not Filament's amber default.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultColors(): array
    {
        return ['primary' => Color::Blue];
    }

    /**
     * The gray palette applied to every panel.
     *
     * Defaults to Color::Slate — panel-base's standard neutral. Override in a
     * panel provider to use a custom gray while keeping the rest of panel-base's
     * chrome, e.g. to pin only the 900 card-background shade:
     *
     *   protected function getGrayColor(): array|string
     *   {
     *       // array_replace, not spread — spread reindexes the integer shade keys.
     *       return array_replace(Color::Slate, [900 => '22, 24, 28']); // #16181C
     *   }
     *
     * Returning Color::Slate preserves the original behaviour, so existing
     * consumers are unaffected.
     *
     * @return array<int, string>|string
     */
    protected function getGrayColor(): array|string
    {
        return Color::Slate;
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

        return $colors;
    }

    /**
     * Build color array from config values. Returns only the keys present in
     * config; gray + the neutral default are applied by getColorsFromSettings().
     *
     * @return array<string, mixed>
     */
    protected function getColorsFromConfig(): array
    {
        $colors = [];

        foreach (config('filament-panel-base.colors', []) as $name => $hex) {
            $colors[$name] = Color::hex($hex);
        }

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
                    ->label(fn (): string => __('Admin Panel'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url('/'.$adminPanel)
                    ->color('info')
                    ->visible(function () use ($adminPanel): bool {
                        $target = filament()->getPanel($adminPanel, isStrict: false);

                        return $target && (Auth::user()?->canAccessPanel($target) ?? false);
                    })
                    ->sort(50);
            } elseif ($panelId === $adminPanel) {
                $items[] = Action::make('user-dashboard')
                    ->label(fn (): string => __('My Dashboard'))
                    ->icon('heroicon-o-squares-2x2')
                    ->url('/'.$dashboardPanel)
                    ->color('primary')
                    ->visible(function () use ($dashboardPanel): bool {
                        $target = filament()->getPanel($dashboardPanel, isStrict: false);

                        return $target && (Auth::user()?->canAccessPanel($target) ?? false);
                    })
                    ->sort(50);
            }
        }

        return $items;
    }

    /**
     * Get a small badge identifying the current panel.
     */
    protected function getPanelBadge(bool $centered = false): View
    {
        return view('filament-panel-base::components.panel-badge', [
            'label' => __($this->titleBadgeConfig['label']),
            'color' => $this->titleBadgeConfig['color'] ?? 'primary',
            'icon' => $this->titleBadgeConfig['icon'] ?? null,
            'centered' => $centered,
        ]);
    }

    protected function getVisitWebsiteButton(): View
    {
        return view('filament-panel-base::components.visit-website-button', [
            'label' => $this->visitWebsiteLabel ?? __('Visit Website'),
        ]);
    }

    /**
     * Render the locale switcher dropdown for the topbar.
     */
    protected function getLocaleToggle(): string
    {
        return view('filament-panel-base::components.locale-switcher', [
            'locales' => SetLocale::getLocales(),
            'currentLocale' => app()->getLocale(),
        ])->render();
    }

    /**
     * Get the shared middleware stack used by all panels.
     *
     * `TrackVisit` is appended only when AnalyticsSettings is loadable AND
     * its `enabled` + `track_visits` flags are on. The settings lookup is
     * wrapped because fresh installs (no migration yet) and tests that boot
     * without a DB would otherwise throw before the migrate command runs.
     */
    protected function getSharedMiddleware(): array
    {
        $middleware = [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
            SetLocale::class,
        ];

        if ($this->shouldTrackVisits()) {
            $middleware[] = TrackVisit::class;
        }

        return $middleware;
    }

    /**
     * Decide whether to mount the TrackVisit middleware. Default off when
     * the settings row is missing — keeps cold-boot safe before migrations
     * have been run on a fresh app.
     */
    protected function shouldTrackVisits(): bool
    {
        try {
            $settings = app(AnalyticsSettings::class);

            return $settings->enabled && $settings->track_visits;
        } catch (\Throwable) {
            return false;
        }
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
            fn (): View => view('filament-panel-base::components.sidebar-collapse-remover'),
        );

        $panel->renderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            fn (): View => view('filament-panel-base::components.sidebar-collapse-button', [
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
            fn (): View => view('filament-panel-base::components.sidebar-slideover-styles', [
                'collapseToIcons' => $this->sidebarCollapseToIcons,
            ]),
        );
    }
}
