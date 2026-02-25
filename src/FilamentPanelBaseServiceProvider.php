<?php

namespace Codenzia\FilamentPanelBase;

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
        $this->loadViewsFrom(__DIR__ . '/../resources/views', static::$viewNamespace);

        // Register Blade component namespace
        Blade::componentNamespace('Codenzia\\FilamentPanelBase\\View\\Components', static::$viewNamespace);
    }
}
