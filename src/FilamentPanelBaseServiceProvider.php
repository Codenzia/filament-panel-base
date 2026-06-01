<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase;

use Closure;
use Codenzia\FilamentPanelBase\Analytics\Console\PruneAnalyticsCommand;
use Codenzia\FilamentPanelBase\Analytics\Console\RollupAnalyticsCommand;
use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;
use Codenzia\FilamentPanelBase\Analytics\Subscribers\AuthEventSubscriber;
use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\OtpDriverManager;
use Codenzia\FilamentPanelBase\Auth\Livewire\ForgotPassword;
use Codenzia\FilamentPanelBase\Auth\Livewire\Login;
use Codenzia\FilamentPanelBase\Auth\Livewire\ManageSocialAccounts;
use Codenzia\FilamentPanelBase\Auth\Livewire\Register;
use Codenzia\FilamentPanelBase\Auth\Livewire\ResetPassword;
use Codenzia\FilamentPanelBase\Auth\Livewire\VerifyEmailNotice;
use Codenzia\FilamentPanelBase\Auth\Livewire\VerifyOtp;
use Codenzia\FilamentPanelBase\Auth\Observers\AuthUserObserver;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\CommandPalette\CommandPaletteRegistry;
use Codenzia\FilamentPanelBase\CommandPalette\Contributors\FilamentNavigationContributor;
use Codenzia\FilamentPanelBase\CommandPalette\Contributors\RecentViewsContributor;
use Codenzia\FilamentPanelBase\CommandPalette\Listeners\RecordFilamentPageView;
use Codenzia\FilamentPanelBase\CommandPalette\Livewire\CommandPalette;
use Codenzia\FilamentPanelBase\CommandPalette\Settings\CommandPaletteSettings;
use Codenzia\FilamentPanelBase\Commands\DemoPasswordCommand;
use Codenzia\FilamentPanelBase\Commands\EnableTranslationsCommand;
use Codenzia\FilamentPanelBase\Commands\InstallAuthCommand;
use Codenzia\FilamentPanelBase\Commands\ScanTranslationsCommand;
use Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage;
use Codenzia\FilamentPanelBase\Sessions\Listeners\DetectNewDeviceLogin;
use Codenzia\FilamentPanelBase\Sessions\Livewire\DeviceSessionList;
use Codenzia\FilamentPanelBase\Sessions\Settings\SessionManagementSettings;
use Codenzia\FilamentPanelBase\TwoFactor\Livewire\TwoFactorChallenge;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentPanelBaseServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-panel-base';

    public static string $viewNamespace = 'filament-panel-base';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile(['filament-panel-base', 'disposable_emails'])
            ->hasTranslations()
            ->hasCommands([
                EnableTranslationsCommand::class,
                ScanTranslationsCommand::class,
                InstallAuthCommand::class,
                DemoPasswordCommand::class,
                RollupAnalyticsCommand::class,
                PruneAnalyticsCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('codenzia/filament-panel-base');
            });

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(OtpDriverManager::class);

        // Force AuthenticationSettings to behave as a true singleton so
        // fluent overrides set via FilamentPanelBasePlugin::withAuthentication()
        // survive across container resolutions. Spatie's default resolver
        // does NOT cache instances — each `app(AuthenticationSettings::class)`
        // returns a fresh instance loaded from DB, which would silently undo
        // any in-memory override.
        $this->app->singleton(
            AuthenticationSettings::class
        );

        // Same singleton treatment for AnalyticsSettings.
        $this->app->singleton(
            AnalyticsSettings::class
        );

        // And TwoFactorSettings — runtime overrides applied via
        // FilamentPanelBasePlugin::withTwoFactor() must survive across
        // container resolutions.
        $this->app->singleton(
            TwoFactorSettings::class
        );

        // And SessionManagementSettings for the same reason.
        $this->app->singleton(
            SessionManagementSettings::class
        );

        // And CommandPaletteSettings for the same reason.
        $this->app->singleton(
            CommandPaletteSettings::class
        );

        // The CommandPaletteRegistry is the central place hosts and consumer
        // plugins push extra actions into. Singleton because every contributor
        // is added once at boot time.
        $this->app->singleton(CommandPaletteRegistry::class);
    }

    public function packageBooted(): void
    {
        $this->configureTranslatablePlaceholders();
        $this->bootAuthModule();
        $this->bootAnalyticsModule();
        $this->bootTwoFactorModule();
        $this->bootSessionManagementModule();
        $this->bootCommandPaletteModule();
        $this->bootDemoModule();
        $this->bootBrandingFooter();

        // Register settings migration path so spatie/laravel-settings discovers them
        $settingsMigrationsPath = __DIR__.'/../database/settings';
        if (is_dir($settingsMigrationsPath)) {
            $paths = config('settings.migrations_paths', []);
            $paths[] = $settingsMigrationsPath;
            config(['settings.migrations_paths' => $paths]);
        }

        // Register Blade component namespace
        Blade::componentNamespace('Codenzia\\FilamentPanelBase\\View\\Components', static::$viewNamespace);

        // Register flag-icons CSS with Filament's asset system.
        // Auto-injected on Filament panels via @filamentStyles.
        FilamentAsset::register([
            Css::make('flag-icons', __DIR__.'/../resources/dist/flag-icons.css'),
        ], 'codenzia/filament-panel-base');

        // Publish the SVG flags directory alongside the CSS.
        // The patched CSS uses url(./flags/...) so SVGs must land as siblings.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/dist/flags' => public_path('css/codenzia/filament-panel-base/flags'),
            ], 'filament-panel-base-assets');

            // Publish the theme CSS for Tailwind v4 @theme integration.
            // Projects @import this in their resources/css/app.css.
            $this->publishes([
                __DIR__.'/../resources/css/theme.css' => resource_path('css/vendor/filament-panel-base/theme.css'),
            ], 'filament-panel-base-theme');
        }
    }

    /**
     * Boot the Auth module: register Livewire components, conditionally
     * load auth routes, subscribe moderation observers. All steps are
     * cheap and no-op when the host hasn't opted into auth via
     * `FilamentPanelBasePlugin::withAuthentication()`.
     */
    protected function bootAuthModule(): void
    {
        if (class_exists(Livewire::class)) {
            $authComponents = [
                'filament-panel-base::auth.register' => Register::class,
                'filament-panel-base::auth.login' => Login::class,
                'filament-panel-base::auth.verify-otp' => VerifyOtp::class,
                'filament-panel-base::auth.verify-email-notice' => VerifyEmailNotice::class,
                'filament-panel-base::auth.forgot-password' => ForgotPassword::class,
                'filament-panel-base::auth.reset-password' => ResetPassword::class,
                'filament-panel-base::auth.manage-social-accounts' => ManageSocialAccounts::class,
            ];

            foreach ($authComponents as $alias => $class) {
                Livewire::component($alias, $class);
            }

            // Livewire v4's Finder::resolveClassComponentClassName only checks
            // registered namespaces for `ns::component` names, never explicit
            // classComponents entries, so the calls above register the alias
            // but lookups by alias fail. Register a missing-component resolver
            // that mirrors classComponents so namespaced aliases resolve.
            if (method_exists(Livewire::getFacadeRoot(), 'resolveMissingComponent')) {
                Livewire::resolveMissingComponent(function (string $name) use ($authComponents): ?string {
                    return $authComponents[$name] ?? null;
                });
            }
        }

        if ((bool) config('filament-panel-base.auth.routes.enabled', true)) {
            $this->loadAuthRoutes();
        }

        if ((bool) config('filament-panel-base.locale.routes.enabled', true)) {
            $this->loadLocaleRoutes();
        }

        Event::subscribe(AuthUserObserver::class);

        if ($this->app->runningInConsole()) {
            $stamp = date('Y_m_d_His');

            $this->publishes([
                __DIR__.'/../database/migrations/create_otp_codes_table.php.stub' => database_path(
                    "migrations/{$stamp}_create_otp_codes_table.php"
                ),
                __DIR__.'/../database/migrations/create_social_accounts_table.php.stub' => database_path(
                    "migrations/{$stamp}_create_social_accounts_table.php"
                ),
                __DIR__.'/../database/migrations/migrate_legacy_social_columns.php.stub' => database_path(
                    'migrations/'.date('Y_m_d_His', strtotime('+1 second')).'_migrate_legacy_social_columns.php'
                ),
            ], 'filament-panel-base-auth-migrations');

            $this->publishes([
                __DIR__.'/../database/migrations/create_demo_settings_table.php.stub' => database_path(
                    "migrations/{$stamp}_create_demo_settings_table.php"
                ),
            ], 'filament-panel-base-demo-migrations');
        }
    }

    /**
     * Boot the Analytics module: subscribe the AuthEventSubscriber so package
     * + Laravel auth events get persisted as auth_events rows, publish the
     * three table migrations under a feature-scoped tag, and schedule the
     * hourly rollup + nightly prune. Everything stays a cheap no-op when the
     * host hasn't opted in via FilamentPanelBasePlugin::withAnalytics().
     *
     * The subscriber itself reads AnalyticsSettings on every call, so the
     * runtime kill-switch (settings.analytics.enabled=false) takes effect
     * without redeploying.
     */
    protected function bootAnalyticsModule(): void
    {
        Event::subscribe(AuthEventSubscriber::class);

        // Auto-discover the three analytics table migrations. Living in a
        // dedicated subdirectory (not the same folder as the auth/demo stubs)
        // means `loadMigrationsFrom()` only picks up the analytics files —
        // the publish-required stubs alongside it keep their explicit-publish
        // behaviour. Hosts run `php artisan migrate` and the tables appear;
        // no `vendor:publish` step.
        //
        // Schema customisation path for the rare consumer who needs it:
        // write a host-side ALTER migration, or override the Visit/AuthEvent
        // models with a subclass.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/analytics');

        if ($this->app->runningInConsole()) {
            // Schedule the rollup + prune commands. Gated on runningInConsole
            // so we don't resolve the Schedule binding during a normal HTTP
            // request — keeps cold-start cheap.
            $this->app->afterResolving(
                \Illuminate\Console\Scheduling\Schedule::class,
                function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
                    $schedule->command('filament-panel-base:analytics:rollup')
                        ->hourly()
                        ->withoutOverlapping();

                    $schedule->command('filament-panel-base:analytics:prune')
                        ->dailyAt('03:15')
                        ->withoutOverlapping();
                },
            );
        }
    }

    /**
     * Boot the Two-Factor module: register the challenge Livewire alias and
     * auto-load the `users` table column migration. Idempotent — the
     * migration adds three columns guarded by hasColumn() so hosts who
     * already had Fortify-style 2FA columns are unaffected.
     *
     * Optional dependency: pragmarx/google2fa + bacon/bacon-qr-code. The
     * services throw clear RuntimeExceptions when those aren't installed,
     * so this boot step itself is safe even without them.
     */
    protected function bootTwoFactorModule(): void
    {
        if (class_exists(Livewire::class)) {
            $alias = 'filament-panel-base::two-factor.challenge';

            Livewire::component($alias, TwoFactorChallenge::class);

            if (method_exists(Livewire::getFacadeRoot(), 'resolveMissingComponent')) {
                Livewire::resolveMissingComponent(function (string $name) use ($alias): ?string {
                    return $name === $alias ? TwoFactorChallenge::class : null;
                });
            }
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/two_factor');
    }

    /**
     * Boot the Session & Device Management module: register the
     * DeviceSessionList Livewire alias and subscribe the new-device-login
     * detector. The detector is gated at runtime by settings — listening
     * with no DB is safe (the listener short-circuits cheaply).
     */
    protected function bootSessionManagementModule(): void
    {
        if (class_exists(Livewire::class)) {
            $alias = 'filament-panel-base::sessions.device-session-list';

            Livewire::component($alias, DeviceSessionList::class);

            if (method_exists(Livewire::getFacadeRoot(), 'resolveMissingComponent')) {
                Livewire::resolveMissingComponent(function (string $name) use ($alias): ?string {
                    return $name === $alias ? DeviceSessionList::class : null;
                });
            }
        }

        Event::listen(
            \Illuminate\Auth\Events\Login::class,
            DetectNewDeviceLogin::class,
        );
    }

    /**
     * Boot the Command Palette module: register the Livewire alias, load
     * the recent_views migration, register the default Filament-navigation
     * contributor + recent-views contributor, and hook the modal into
     * every Filament panel via the BODY_END render hook.
     *
     * The render hook itself checks at render time whether any plugin on
     * the current panel has opted into the palette. That makes the module
     * safe to boot unconditionally — apps that never call
     * `->withCommandPalette()` pay no rendering cost.
     */
    protected function bootCommandPaletteModule(): void
    {
        if (class_exists(Livewire::class)) {
            $alias = 'filament-panel-base::command-palette';

            Livewire::component($alias, CommandPalette::class);

            if (method_exists(Livewire::getFacadeRoot(), 'resolveMissingComponent')) {
                Livewire::resolveMissingComponent(function (string $name) use ($alias): ?string {
                    return $name === $alias ? CommandPalette::class : null;
                });
            }
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/command_palette');

        // Wire the two default contributors into the registry. Hosts and
        // consumer plugins can call $registry->register(...) afterward to
        // push their own.
        $registry = $this->app->make(CommandPaletteRegistry::class);
        $registry->register($this->app->make(FilamentNavigationContributor::class));
        $registry->register($this->app->make(RecentViewsContributor::class));

        // Auto-record record-page views via Filament's serving hook so the
        // command palette can show a "Recent" group without the host
        // wiring up each resource page manually. Guarded by container
        // binding (not class_exists) because the Filament facade exists
        // on the autoloader but resolving it requires the Filament
        // service provider — not loaded in unit tests.
        if ($this->app->bound('filament')) {
            try {
                \Filament\Facades\Filament::serving(function (): void {
                    try {
                        $this->app->make(RecordFilamentPageView::class)->handle();
                    } catch (\Throwable) {
                        // Recording is best-effort.
                    }
                });
            } catch (\Throwable) {
                // Filament not fully booted — skip the auto-recorder.
            }
        }

        // Inject the modal at the end of every Filament page body. The
        // closure checks the current panel for the FilamentPanelBasePlugin
        // and only renders when withCommandPalette() was called — so apps
        // that never opt in pay zero cost.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            function (): string {
                if (! $this->commandPaletteEnabledForCurrentPanel()) {
                    return '';
                }

                return Blade::render(<<<'BLADE'
                    @include('filament-panel-base::command-palette.modal')
                BLADE);
            },
        );
    }

    /**
     * True when the current Filament panel's FilamentPanelBasePlugin has
     * `->withCommandPalette()` enabled. Returns false defensively when
     * Filament hasn't booted, the plugin isn't registered, or any check
     * throws.
     */
    protected function commandPaletteEnabledForCurrentPanel(): bool
    {
        if (! function_exists('filament')) {
            return false;
        }

        try {
            $panel = filament()->getCurrentPanel();

            if ($panel === null) {
                return false;
            }

            $plugin = $panel->getPlugin('filament-panel-base');

            return $plugin instanceof FilamentPanelBasePlugin
                && $plugin->isCommandPaletteEnabled();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Boot the Demo module: register the /demo Livewire route and component
     * when explicitly opted in via filament-panel-base.demo.enabled. No-op
     * otherwise so production deployments stay clean by default.
     *
     * The Livewire component used at /demo is read from
     * config('filament-panel-base.demo.component') — defaults to the package's
     * DemoPage. Hosts swap it for a subclass to override data collection
     * (collectStats, collectUsers, canLogInAs, ...).
     */
    protected function bootDemoModule(): void
    {
        if (! (bool) config('filament-panel-base.demo.enabled', false)) {
            return;
        }

        // Defer the actual route + component registration to `booted()` so
        // hosts can swap the Livewire component class from their own
        // AppServiceProvider via:
        //
        //   config(['filament-panel-base.demo.component' => \App\Livewire\DemoPage::class]);
        //
        // Without the defer, the route binds to the package default before
        // the host provider has had a chance to set the override.
        $this->app->booted(function (): void {
            /** @var class-string<DemoPage> $component */
            $component = (string) config(
                'filament-panel-base.demo.component',
                DemoPage::class,
            );

            if (! class_exists($component)) {
                // Defensive: fall back to the package default if the host
                // misconfigures the component class.
                $component = DemoPage::class;
            }

            if (class_exists(Livewire::class)) {
                Livewire::component('filament-panel-base::demo.page', $component);
            }

            $uri = (string) config('filament-panel-base.demo.route', '/demo');
            /** @var array<int, string> $middleware */
            $middleware = (array) config('filament-panel-base.demo.middleware', ['web']);

            Route::middleware($middleware)
                ->get($uri, $component)
                ->name('filament-panel-base.demo');
        });
    }

    /**
     * Register the "Powered by Codenzia" render hook on every Filament panel
     * page. Hidden by setting CODENZIA_BRANDING=false in .env. Subtle by
     * design — small muted line at the very bottom of the panel chrome.
     */
    protected function bootBrandingFooter(): void
    {
        if (! (bool) config('filament-panel-base.branding.powered_by_enabled', true)) {
            return;
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::FOOTER,
            fn (): string => Blade::render(<<<'BLADE'
                <div class="py-3 text-center text-xs text-gray-400 dark:text-gray-600">
                    Powered by
                    <a href="https://www.codenzia.com" target="_blank" rel="noopener"
                       class="font-medium hover:text-primary-500 transition">Codenzia</a>
                </div>
            BLADE)
        );
    }

    protected function loadAuthRoutes(): void
    {
        $routesFile = __DIR__.'/../routes/auth.php';

        if (! file_exists($routesFile)) {
            return;
        }

        $prefix = (string) config('filament-panel-base.auth.routes.prefix', '');
        $name = (string) config('filament-panel-base.auth.routes.name', '');
        /** @var array<int, string> $middleware */
        $middleware = (array) config('filament-panel-base.auth.routes.middleware', ['web']);

        Route::middleware($middleware)
            ->prefix($prefix)
            ->name($name)
            ->group($routesFile);
    }

    protected function loadLocaleRoutes(): void
    {
        $routesFile = __DIR__.'/../routes/locale.php';

        if (! file_exists($routesFile)) {
            return;
        }

        $prefix = (string) config('filament-panel-base.locale.routes.prefix', '');
        /** @var array<int, string> $middleware */
        $middleware = (array) config('filament-panel-base.locale.routes.middleware', ['web']);

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group($routesFile);
    }

    /**
     * Show the default-locale value as a placeholder when editing translatable
     * fields in a non-default locale. Applied globally via configureUsing so
     * individual resources don't need any changes.
     */
    protected function configureTranslatablePlaceholders(): void
    {
        $placeholderFn = $this->makeTranslatablePlaceholder();

        TextInput::configureUsing($placeholderFn);
        Textarea::configureUsing($placeholderFn);
        RichEditor::configureUsing($placeholderFn);
    }

    protected function makeTranslatablePlaceholder(): Closure
    {
        return function ($component): void {
            $component->placeholder(function ($component): ?string {
                $livewire = $component->getLivewire();

                if (! method_exists($livewire, 'getActiveSchemaLocale')) {
                    return null;
                }

                $activeLocale = $livewire->getActiveSchemaLocale();
                $defaultLocale = config('app.locale', 'en');

                if (! $activeLocale || $activeLocale === $defaultLocale) {
                    return null;
                }

                $record = $component->getRecord();

                if (! $record instanceof Model || ! method_exists($record, 'isTranslatableAttribute')) {
                    return null;
                }

                $field = $component->getName();

                if (! $record->isTranslatableAttribute($field)) {
                    return null;
                }

                $value = $record->getTranslation($field, $defaultLocale, false);

                return is_string($value) && $value !== '' ? $value : null;
            });
        };
    }
}
