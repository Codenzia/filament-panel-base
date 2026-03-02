<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase;

use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Model;
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
            ->hasCommands([
                \Codenzia\FilamentPanelBase\Commands\EnableTranslationsCommand::class,
                \Codenzia\FilamentPanelBase\Commands\ScanTranslationsCommand::class,
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

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {
        $this->configureTranslatablePlaceholders();

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
            $component->placeholder(function () use ($component): ?string {
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
