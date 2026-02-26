<?php

    declare(strict_types=1);

    namespace Codenzia\FilamentPanelBase;

    use Filament\Support\Assets\Css;
    use Filament\Support\Facades\FilamentAsset;
    use Illuminate\Support\Facades\Blade;
    use Spatie\LaravelPackageTools\Commands\InstallCommand;
    use Spatie\LaravelPackageTools\Package;
    use Spatie\LaravelPackageTools\PackageServiceProvider;

    class FilamentPanelBaseServiceProvider extends PackageServiceProvider
    {
        public static string $name = 'filament-panel-base';

        public static string $viewNamespace = 'panel-base';

        public function configurePackage(Package $package): void
        {
            $package->name(static::$name)
                ->hasConfigFile()
                ->hasInstallCommand(function (InstallCommand $command) {
                    $command
                        ->publishConfigFile()
                        ->askToStarRepoOnGitHub('codenzia/filament-panel-base');
                });

            if (file_exists($package->basePath('/../resources/views'))) {
                $package->hasViews(static::$viewNamespace);
            }
        }

        public function packageRegistered(): void {}

        public function packageBooted(): void
        {
            // Register Blade component namespace
            Blade::componentNamespace('Codenzia\\FilamentPanelBase\\View\\Components', static::$viewNamespace);

            // Register flag-icons CSS with Filament's asset system.
            // Auto-injected on Filament panels via @filamentStyles.
            FilamentAsset::register([
                Css::make('flag-icons', __DIR__ . '/../resources/dist/flag-icons.css'),
                Css::make('phone-input', __DIR__ . '/../resources/css/phone-input.css'),
            ], 'codenzia/filament-panel-base');

            // Publish the SVG flags directory alongside the CSS.
            // The patched CSS uses url(./flags/...) so SVGs must land as siblings.
            if ($this->app->runningInConsole()) {
                $this->publishes([
                    __DIR__ . '/../resources/dist/flags' => public_path('css/codenzia/filament-panel-base/flags'),
                ], 'filament-panel-base-assets');

                // Publish the theme CSS for Tailwind v4 @theme integration.
                // Projects @import this in their resources/css/app.css.
                $this->publishes([
                    __DIR__ . '/../resources/css/theme.css' => resource_path('css/vendor/filament-panel-base/theme.css'),
                ], 'filament-panel-base-theme');
            }
        }
    }
