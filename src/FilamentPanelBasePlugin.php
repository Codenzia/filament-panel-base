<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase;

use Codenzia\FilamentPanelBase\Analytics\AnalyticsPlugin;
use Codenzia\FilamentPanelBase\Analytics\Filament\Pages\AnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\AuthFunnelWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\DeviceTypeWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\ErrorRateSparklineWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\FailedLoginsChartWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\GeoBreakdownWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\SlowestPagesWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\TopPagesWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\VisitorsChartWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\VisitorsTodayWidget;
use Codenzia\FilamentPanelBase\Auth\AuthenticationPlugin;
use Codenzia\FilamentPanelBase\Auth\Filament\Pages\Login;
use Codenzia\FilamentPanelBase\Auth\Filament\Pages\ManageAuthenticationSettings;
use Codenzia\FilamentPanelBase\Auth\Filament\Pages\Register;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\CommandPalette\CommandPalettePlugin;
use Codenzia\FilamentPanelBase\CommandPalette\CommandPaletteRegistry;
use Codenzia\FilamentPanelBase\Contracts\ProvidesThemeColors;
use Codenzia\FilamentPanelBase\Filament\Pages\ManageAppearanceSettings;
use Codenzia\FilamentPanelBase\Filament\Pages\ManageDemoSettings;
use Codenzia\FilamentPanelBase\Filament\Resources\TranslationResource;
use Codenzia\FilamentPanelBase\Filament\Resources\UserResource;
use Codenzia\FilamentPanelBase\Sessions\SessionManagementPlugin;
use Codenzia\FilamentPanelBase\Support\ThemePresets;
use Codenzia\FilamentPanelBase\TwoFactor\Filament\Pages\TwoFactorChallengePage;
use Codenzia\FilamentPanelBase\TwoFactor\TwoFactorPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

/**
 * Filament panel plugin that provides shared multi-panel configuration.
 */
class FilamentPanelBasePlugin implements Plugin
{
    protected ?string $settingsClass = null;

    protected ?\Closure $settingsResolver = null;

    protected bool $translationsEnabled = false;

    protected bool $userManagementEnabled = false;

    protected ?string $appearanceSettingsPageClass = null;

    protected ?AuthenticationPlugin $authentication = null;

    protected ?AnalyticsPlugin $analytics = null;

    protected ?TwoFactorPlugin $twoFactor = null;

    protected ?SessionManagementPlugin $sessionManagement = null;

    protected ?CommandPalettePlugin $commandPalette = null;

    protected ?string $filamentAnalyticsPageClass = null;

    protected ?string $twoFactorChallengePageClass = null;

    protected bool $filamentAuthLoginEnabled = false;

    protected bool $filamentAuthRegisterEnabled = false;

    protected ?string $filamentAuthSettingsPageClass = null;

    protected ?string $demoSettingsPageClass = null;

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
     * Enable the shared admin Users resource on this panel — opt-in, because not
     * every panel is an admin panel (a customer dashboard just never calls this).
     *
     * Generalized from the fleet's many hand-rolled UserResources so the UI is
     * consistent and fixed once. Role assignment appears only when
     * spatie/permission is installed; the protected super-admin can't be deleted.
     *
     * Example:
     *
     *   FilamentPanelBasePlugin::make()
     *       ->withUserManagement(
     *           authorize: fn () => auth()->user()?->can('Manage:Users'),
     *           navigationGroup: 'Users & roles',
     *       );
     *
     * @param  \Closure(): bool|null  $authorize  access gate (default: super-admin when laravel-superadmin is present)
     * @param  \Closure(): array|null  $extraSchema  app-specific form components appended after the built-in tabs
     * @param  \Closure(array): array|null  $tableColumns  receives the default columns, returns the final set (append/prepend/reorder)
     * @param  \Closure(array): array|null  $tableFilters  same, for table filters
     * @param  \Closure(array): array|null  $recordActions  same, for the row actions
     */
    public function withUserManagement(
        ?\Closure $authorize = null,
        ?\Closure $extraSchema = null,
        ?\Closure $tableColumns = null,
        ?\Closure $tableFilters = null,
        ?\Closure $recordActions = null,
        ?string $navigationGroup = null,
        ?string $navigationIcon = null,
        ?int $navigationSort = null,
        ?string $model = null,
    ): static {
        $this->userManagementEnabled = true;

        // Closures live on the resource (statics), not config — so config:cache stays serializable.
        UserResource::$authorizeUsing = $authorize;
        UserResource::$extraSchemaUsing = $extraSchema;
        UserResource::$columnsUsing = $tableColumns;
        UserResource::$filtersUsing = $tableFilters;
        UserResource::$recordActionsUsing = $recordActions;

        // Scalars are safe to push through config for the resource's static getters to read.
        $overrides = array_filter([
            'navigation_group' => $navigationGroup,
            'navigation_icon' => $navigationIcon,
            'navigation_sort' => $navigationSort,
            'model' => $model,
        ], fn ($v) => $v !== null);
        if ($overrides !== []) {
            config(['filament-panel-base.user_management' => array_merge(
                (array) config('filament-panel-base.user_management', []),
                $overrides,
            )]);
        }

        return $this;
    }

    /**
     * Whether the Users resource has been activated for this panel.
     */
    public function isUserManagementEnabled(): bool
    {
        return $this->userManagementEnabled;
    }

    /**
     * Enable the shared "Appearance" settings page — opt-in. A UI over the panel's
     * own settings instance (whatever ->settingsUsing()/->settingsClass() wired):
     * edit app name, tagline, logo, favicon, theme preset + colors live, instead
     * of editing the settings row in code/tinker.
     *
     * Example:
     *
     *   FilamentPanelBasePlugin::make()
     *       ->settingsUsing(fn () => app(AppSettings::class))
     *       ->withAppearanceSettings(authorize: fn () => auth()->user()?->isSuperAdmin());
     *
     * @param  class-string<ManageAppearanceSettings>|null  $page  a host subclass, for custom access/fields
     * @param  \Closure(): bool|null  $authorize  access gate (default: super-admin when laravel-superadmin is present)
     */
    public function withAppearanceSettings(
        ?string $page = null,
        ?\Closure $authorize = null,
        ?string $navigationGroup = null,
        ?string $navigationIcon = null,
        ?int $navigationSort = null,
    ): static {
        $this->appearanceSettingsPageClass = $page ?? ManageAppearanceSettings::class;

        // Set the gate on the actual page class used (honours a host subclass).
        ($this->appearanceSettingsPageClass)::$authorizeUsing = $authorize;

        $overrides = array_filter([
            'navigation_group' => $navigationGroup,
            'navigation_icon' => $navigationIcon,
            'navigation_sort' => $navigationSort,
        ], fn ($v) => $v !== null);
        if ($overrides !== []) {
            config(['filament-panel-base.appearance' => array_merge(
                (array) config('filament-panel-base.appearance', []),
                $overrides,
            )]);
        }

        return $this;
    }

    public function hasAppearanceSettingsPage(): bool
    {
        return $this->appearanceSettingsPageClass !== null;
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
     * Configure the Analytics module — visitor tracking, auth-event recording,
     * resource-usage stats, plus the nightly retention prune. The closure
     * receives an {@see AnalyticsPlugin} for fluent overrides; values applied
     * via the fluent API override AnalyticsSettings for the request lifecycle.
     *
     * Example:
     *
     *   FilamentPanelBasePlugin::make()
     *       ->withAnalytics(fn ($a) => $a
     *           ->ipAnonymization('truncate')
     *           ->retainRawDays(30)
     *           ->writeQueue('analytics')
     *       );
     */
    public function withAnalytics(?\Closure $callback = null): static
    {
        $this->analytics = app(AnalyticsPlugin::class);

        if ($callback !== null) {
            $callback($this->analytics);
        }

        $this->analytics->enable()->apply();

        return $this;
    }

    public function getAnalytics(): ?AnalyticsPlugin
    {
        return $this->analytics;
    }

    public function isAnalyticsEnabled(): bool
    {
        return $this->analytics?->isEnabled() ?? false;
    }

    /**
     * Configure the Two-Factor Authentication module — issuer name, recovery
     * code count, acceptance window, role-based mandatory enrolment. The
     * closure receives a {@see TwoFactorPlugin} for fluent overrides;
     * values applied via the fluent API override TwoFactorSettings for the
     * request lifecycle. The challenge route ('/two-factor-challenge') is
     * always registered when this method is called.
     *
     * Example:
     *
     *   FilamentPanelBasePlugin::make()
     *       ->withTwoFactor(fn ($tf) => $tf
     *           ->issuer('Acme Inc.')
     *           ->acceptanceWindow(2)
     *           ->requireForRoles(['super_admin'])
     *       );
     */
    public function withTwoFactor(?\Closure $callback = null): static
    {
        $this->twoFactor = app(TwoFactorPlugin::class);

        if ($callback !== null) {
            $callback($this->twoFactor);
        }

        $this->twoFactor->enable()->apply();

        return $this;
    }

    public function getTwoFactor(): ?TwoFactorPlugin
    {
        return $this->twoFactor;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactor?->isEnabled() ?? false;
    }

    /**
     * Configure the Session & Device Management module — listing of active
     * sessions in the profile slide-over, per-row revoke, "sign out
     * everywhere else", and optional new-device-login notification. The
     * closure receives a {@see SessionManagementPlugin} for fluent
     * overrides; values applied via the fluent API override
     * SessionManagementSettings for the request lifecycle.
     *
     * REQUIRES `SESSION_DRIVER=database` to actually surface sessions —
     * the profile tab degrades to a friendly notice otherwise.
     *
     * Example:
     *
     *   FilamentPanelBasePlugin::make()
     *       ->withSessionManagement(fn ($s) => $s
     *           ->notifyOnNewDevice()
     *           ->idleThresholdMinutes(15)
     *       );
     */
    public function withSessionManagement(?\Closure $callback = null): static
    {
        $this->sessionManagement = app(SessionManagementPlugin::class);

        if ($callback !== null) {
            $callback($this->sessionManagement);
        }

        $this->sessionManagement->enable()->apply();

        return $this;
    }

    public function getSessionManagement(): ?SessionManagementPlugin
    {
        return $this->sessionManagement;
    }

    public function isSessionManagementEnabled(): bool
    {
        return $this->sessionManagement?->isEnabled() ?? false;
    }

    /**
     * Configure the Command Palette (Cmd-K) module — recent-view tracking,
     * keyboard hint, action limits. The closure receives a
     * {@see CommandPalettePlugin} for fluent overrides; values applied via
     * the fluent API override CommandPaletteSettings for the request
     * lifecycle.
     *
     * The palette renders on every Filament panel page via a render hook
     * once this method is called. Host plugins can push extra actions by
     * resolving the {@see CommandPaletteRegistry}
     * singleton and calling `register()`.
     *
     * Example:
     *
     *   FilamentPanelBasePlugin::make()
     *       ->withCommandPalette(fn ($c) => $c
     *           ->hotkeyLabel('⌘K')
     *           ->recentViewLimit(15)
     *       );
     */
    public function withCommandPalette(?\Closure $callback = null): static
    {
        $this->commandPalette = app(CommandPalettePlugin::class);

        if ($callback !== null) {
            $callback($this->commandPalette);
        }

        $this->commandPalette->enable()->apply();

        return $this;
    }

    public function getCommandPalette(): ?CommandPalettePlugin
    {
        return $this->commandPalette;
    }

    public function isCommandPaletteEnabled(): bool
    {
        return $this->commandPalette?->isEnabled() ?? false;
    }

    /**
     * Mount a Filament-chrome wrapper around the TwoFactorChallenge Livewire
     * component so the post-login challenge renders inside this panel's auth
     * cards instead of the public Livewire route. Optional — panels that use
     * the public route can skip this entirely.
     *
     * @param  class-string<TwoFactorChallengePage>|null  $page
     */
    public function withFilamentTwoFactorChallengePage(?string $page = null): static
    {
        $this->twoFactorChallengePageClass = $page ?? TwoFactorChallengePage::class;

        return $this;
    }

    public function hasFilamentTwoFactorChallengePage(): bool
    {
        return $this->twoFactorChallengePageClass !== null;
    }

    public function getFilamentTwoFactorChallengePageClass(): ?string
    {
        return $this->twoFactorChallengePageClass;
    }

    /**
     * Mount the in-panel Analytics page (visitors today, 30-day chart, etc.)
     * on this panel. Default page is open to any authenticated user — subclass
     * and override `canAccess()` (or add Shield's `HasPageShield`) for
     * tighter authorisation.
     *
     *     ->withFilamentAnalyticsPage(MyAnalyticsPage::class)
     *
     * @param  class-string<AnalyticsPage>|null  $page
     */
    public function withFilamentAnalyticsPage(?string $page = null): static
    {
        $this->filamentAnalyticsPageClass = $page ?? AnalyticsPage::class;

        return $this;
    }

    public function hasFilamentAnalyticsPage(): bool
    {
        return $this->filamentAnalyticsPageClass !== null;
    }

    public function getFilamentAnalyticsPageClass(): ?string
    {
        return $this->filamentAnalyticsPageClass;
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
     * Register the in-plugin auth-settings admin page on this panel. The page
     * surfaces every {@see AuthenticationSettings}
     * field (registration, verification, OTP, social, throttling) grouped
     * into sections so admins don't need to edit DB rows by hand.
     *
     * Pass a subclass to swap the default page — for example, a host-side
     * subclass that adds Filament Shield's `HasPageShield` trait:
     *
     *     ->withFilamentAuthSettingsPage(MyAuthSettings::class)
     *
     * @param  class-string<ManageAuthenticationSettings>|null  $page
     */
    public function withFilamentAuthSettingsPage(?string $page = null): static
    {
        $this->filamentAuthSettingsPageClass = $page
            ?? ManageAuthenticationSettings::class;

        return $this;
    }

    public function hasFilamentAuthSettingsPage(): bool
    {
        return $this->filamentAuthSettingsPageClass !== null;
    }

    public function getFilamentAuthSettingsPageClass(): ?string
    {
        return $this->filamentAuthSettingsPageClass;
    }

    /**
     * Register the in-plugin Demo Settings admin page on this panel. The page
     * lets admins view, rotate, and copy the share link for the /demo gate
     * password (stored DB-first with .env fallback). Default access check
     * requires the role named in config('filament-panel-base.admin_role').
     *
     * Pass a subclass to tighten the access check or customize the view:
     *
     *     ->withDemoSettingsPage(MyDemoSettings::class)
     *
     * @param  class-string<ManageDemoSettings>|null  $page
     */
    public function withDemoSettingsPage(?string $page = null): static
    {
        $this->demoSettingsPageClass = $page
            ?? ManageDemoSettings::class;

        return $this;
    }

    public function hasDemoSettingsPage(): bool
    {
        return $this->demoSettingsPageClass !== null;
    }

    public function getDemoSettingsPageClass(): ?string
    {
        return $this->demoSettingsPageClass;
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
                TranslationResource::class,
            ]);
        }

        if ($this->userManagementEnabled) {
            $panel->resources([
                UserResource::class,
            ]);
        }

        if ($this->appearanceSettingsPageClass !== null) {
            $panel->pages([$this->appearanceSettingsPageClass]);
        }

        // Honour both the top-level fluent API (preferred) and the legacy
        // AuthenticationPlugin::filamentPanelPages() route (deprecated).
        $loginEnabled = $this->filamentAuthLoginEnabled
            || ($this->authentication?->hasFilamentLoginPage() ?? false);

        $registerEnabled = $this->filamentAuthRegisterEnabled
            || ($this->authentication?->hasFilamentRegisterPage() ?? false);

        if ($loginEnabled) {
            $panel->login(Login::class);
        }

        if ($registerEnabled) {
            $panel->registration(Register::class);
        }

        if ($this->filamentAuthSettingsPageClass !== null) {
            $panel->pages([$this->filamentAuthSettingsPageClass]);
        }

        if ($this->demoSettingsPageClass !== null) {
            $panel->pages([$this->demoSettingsPageClass]);
        }

        if ($this->twoFactorChallengePageClass !== null) {
            // Register the in-panel challenge as a direct Laravel route, NOT
            // via $panel->pages([...]). TwoFactorChallengePage extends
            // SimplePage (auth-style bare layout), and SimplePage — unlike
            // Filament's regular Page — skips the HasRoutes trait that
            // powers registerRoutes(). Going through $panel->pages() would
            // fatal at panel boot with "Method ...::registerRoutes does not
            // exist". $panel->routes() runs inside the panel's path-prefix
            // group BEFORE authMiddleware, which is what the challenge
            // needs (user has passed credentials but Auth::login() hasn't
            // been called yet).
            $pageClass = $this->twoFactorChallengePageClass;
            $panel->routes(function () use ($pageClass): void {
                Route::get('/two-factor-challenge', $pageClass)
                    ->name('pages.two-factor-challenge');
            });
        }

        if ($this->filamentAnalyticsPageClass !== null) {
            $panel->pages([$this->filamentAnalyticsPageClass]);

            // Bind the analytics widgets' Livewire component aliases so
            // AnalyticsPage::getWidgets() can render them (otherwise Livewire
            // errors "component not found"). Use livewireComponents() — NOT
            // widgets() — so they are registered for rendering WITHOUT being
            // added to the panel's widget pool, which would otherwise leak all
            // 9 analytics widgets onto the host's default Dashboard.
            $panel->livewireComponents([
                VisitorsTodayWidget::class,
                ErrorRateSparklineWidget::class,
                VisitorsChartWidget::class,
                AuthFunnelWidget::class,
                FailedLoginsChartWidget::class,
                TopPagesWidget::class,
                SlowestPagesWidget::class,
                GeoBreakdownWidget::class,
                DeviceTypeWidget::class,
            ]);
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
