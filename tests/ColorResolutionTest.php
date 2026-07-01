<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Contracts\ProvidesThemeColors;
use Codenzia\FilamentPanelBase\Providers\BasePanelProvider;
use Filament\Panel;
use Filament\Support\Colors\Color;

/**
 * Minimal concrete provider that exposes the protected color resolver so the
 * layered precedence can be asserted directly.
 */
class ColorResolutionTestProvider extends BasePanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel;
    }

    public function resolveColors(): array
    {
        return $this->getColorsFromSettings();
    }
}

/**
 * Settings double that themes the primary to amber-orange via ProvidesThemeColors,
 * mirroring the fleet apps whose GeneralSettings ships `primary_color = '#FFA812'`.
 */
class AmberThemeSettings implements ProvidesThemeColors
{
    public function getThemeColors(): array
    {
        return ['primary_color' => '#FFA812'];
    }
}

beforeEach(function () {
    config()->set('filament-panel-base.settings_class', null);
    config()->set('filament-panel-base.colors', []);
});

it('falls back to neutral blue (never Filament amber) when nothing is configured', function () {
    $colors = (new ColorResolutionTestProvider(app()))->resolveColors();

    expect($colors['primary'])->toBe(Color::Blue);
});

it('lets per-panel primaryColor() override the config default', function () {
    config()->set('filament-panel-base.colors', ['primary' => '#3b82f6']);

    $provider = (new ColorResolutionTestProvider(app()))->primaryColor(Color::Indigo);

    expect($provider->resolveColors()['primary'])->toBe(Color::Indigo);
});

it('uses the settings model color when the panel does not pin one', function () {
    config()->set('filament-panel-base.settings_class', AmberThemeSettings::class);

    $colors = (new ColorResolutionTestProvider(app()))->resolveColors();

    expect($colors['primary'])->toBe(Color::hex('#FFA812'));
});

it('lets an explicit brandColors() pin win over a ProvidesThemeColors settings model', function () {
    config()->set('filament-panel-base.settings_class', AmberThemeSettings::class);

    $provider = (new ColorResolutionTestProvider(app()))->primaryColor(Color::Indigo);

    // The settings model would theme primary to #FFA812; the explicit pin must win.
    expect($provider->resolveColors()['primary'])->toBe(Color::Indigo);
});
