<?php

use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;

it('can be instantiated via make', function () {
    $plugin = FilamentPanelBasePlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentPanelBasePlugin::class);
});

it('returns correct plugin id', function () {
    $plugin = FilamentPanelBasePlugin::make();

    expect($plugin->getId())->toBe('filament-panel-base');
});

it('resolves null settings when nothing is configured', function () {
    $plugin = FilamentPanelBasePlugin::make();

    expect($plugin->resolveSettings())->toBeNull();
});

it('resolves settings via closure', function () {
    $settings = new stdClass;
    $settings->app_name = 'Test App';

    $plugin = FilamentPanelBasePlugin::make()
        ->settingsUsing(fn () => $settings);

    expect($plugin->resolveSettings())->toBe($settings)
        ->and($plugin->resolveSettings()->app_name)->toBe('Test App');
});

it('resolves settings via class name from config', function () {
    // Use stdClass as a simple test — it always exists
    config(['filament-panel-base.settings_class' => stdClass::class]);

    $plugin = FilamentPanelBasePlugin::make();

    expect($plugin->resolveSettings())->toBeInstanceOf(stdClass::class);
});

it('prefers closure over class name', function () {
    $closureSettings = new stdClass;
    $closureSettings->source = 'closure';

    config(['filament-panel-base.settings_class' => stdClass::class]);

    $plugin = FilamentPanelBasePlugin::make()
        ->settingsUsing(fn () => $closureSettings);

    expect($plugin->resolveSettings()->source)->toBe('closure');
});

it('returns null for non-existent settings class', function () {
    config(['filament-panel-base.settings_class' => 'NonExistent\\Settings\\Class']);

    $plugin = FilamentPanelBasePlugin::make();

    expect($plugin->resolveSettings())->toBeNull();
});

it('returns fluent interface from configuration methods', function () {
    $plugin = FilamentPanelBasePlugin::make();

    expect($plugin->settingsClass('SomeClass'))->toBeInstanceOf(FilamentPanelBasePlugin::class)
        ->and($plugin->settingsUsing(fn () => null))->toBeInstanceOf(FilamentPanelBasePlugin::class);
});
