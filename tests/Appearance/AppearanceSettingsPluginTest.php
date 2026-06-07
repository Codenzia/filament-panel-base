<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Filament\Pages\ManageAppearanceSettings;
use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;

afterEach(function (): void {
    ManageAppearanceSettings::$authorizeUsing = null;
});

it('is opt-in: the Appearance page stays off until withAppearanceSettings() is called', function (): void {
    expect(FilamentPanelBasePlugin::make()->hasAppearanceSettingsPage())->toBeFalse();
    expect(FilamentPanelBasePlugin::make()->withAppearanceSettings()->hasAppearanceSettingsPage())->toBeTrue();
});

it('gates access through the host-supplied authorize closure', function (): void {
    FilamentPanelBasePlugin::make()->withAppearanceSettings(authorize: fn (): bool => false);
    expect(ManageAppearanceSettings::canAccess())->toBeFalse();

    FilamentPanelBasePlugin::make()->withAppearanceSettings(authorize: fn (): bool => true);
    expect(ManageAppearanceSettings::canAccess())->toBeTrue();
});

it('applies navigation overrides via config', function (): void {
    FilamentPanelBasePlugin::make()->withAppearanceSettings(navigationGroup: 'Branding', navigationSort: 5);

    expect(ManageAppearanceSettings::getNavigationGroup())->toBe('Branding');
    expect(ManageAppearanceSettings::getNavigationSort())->toBe(5);
});
