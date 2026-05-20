<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase;

use Closure;
use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\OtpDriverManager;
use Codenzia\FilamentPanelBase\Auth\Observers\AuthUserObserver;
use Codenzia\FilamentPanelBase\Commands\InstallAuthCommand;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
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
                \Codenzia\FilamentPanelBase\Commands\EnableTranslationsCommand::class,
                \Codenzia\FilamentPanelBase\Commands\ScanTranslationsCommand::class,
                InstallAuthCommand::class,
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
            \Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings::class
        );
    }

    public function packageBooted(): void
    {
        $this->configureTranslatablePlaceholders();
        $this->bootAuthModule();
        $this->bootDemoModule();

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
                'filament-panel-base::auth.register' => \Codenzia\FilamentPanelBase\Auth\Livewire\Register::class,
                'filament-panel-base::auth.login' => \Codenzia\FilamentPanelBase\Auth\Livewire\Login::class,
                'filament-panel-base::auth.verify-otp' => \Codenzia\FilamentPanelBase\Auth\Livewire\VerifyOtp::class,
                'filament-panel-base::auth.verify-email-notice' => \Codenzia\FilamentPanelBase\Auth\Livewire\VerifyEmailNotice::class,
                'filament-panel-base::auth.forgot-password' => \Codenzia\FilamentPanelBase\Auth\Livewire\ForgotPassword::class,
                'filament-panel-base::auth.reset-password' => \Codenzia\FilamentPanelBase\Auth\Livewire\ResetPassword::class,
                'filament-panel-base::auth.manage-social-accounts' => \Codenzia\FilamentPanelBase\Auth\Livewire\ManageSocialAccounts::class,
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
            /** @var class-string<\Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage> $component */
            $component = (string) config(
                'filament-panel-base.demo.component',
                \Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage::class,
            );

            if (! class_exists($component)) {
                // Defensive: fall back to the package default if the host
                // misconfigures the component class.
                $component = \Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage::class;
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
