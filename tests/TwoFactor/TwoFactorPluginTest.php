<?php

use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Codenzia\FilamentPanelBase\TwoFactor\TwoFactorPlugin;

beforeEach(function (): void {
    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->issuer = null;
    $settings->recovery_code_count = 8;
    $settings->digits = 6;
    $settings->period = 30;
    $settings->window = 1;
    $settings->require_for_roles = [];
    $settings->remember_device = true;
    $settings->remember_device_days = 30;
    app()->instance(TwoFactorSettings::class, $settings);
});

it('starts disabled until enable() is called', function (): void {
    $plugin = new TwoFactorPlugin;

    expect($plugin->isEnabled())->toBeFalse();
    $plugin->enable();
    expect($plugin->isEnabled())->toBeTrue();
});

it('clamps invalid digit choices to 6', function (): void {
    $plugin = (new TwoFactorPlugin)->digits(9);

    expect($plugin->getOverrides()['digits'])->toBe(6);
});

it('accepts valid digit choices unchanged', function (): void {
    $plugin = (new TwoFactorPlugin)->digits(8);

    expect($plugin->getOverrides()['digits'])->toBe(8);
});

it('clamps period to a sensible range', function (): void {
    $plugin = (new TwoFactorPlugin)->period(5);
    expect($plugin->getOverrides()['period'])->toBe(15);

    $plugin = (new TwoFactorPlugin)->period(9999);
    expect($plugin->getOverrides()['period'])->toBe(120);
});

it('clamps acceptance window to a sensible range', function (): void {
    $plugin = (new TwoFactorPlugin)->acceptanceWindow(-1);
    expect($plugin->getOverrides()['window'])->toBe(0);

    $plugin = (new TwoFactorPlugin)->acceptanceWindow(100);
    expect($plugin->getOverrides()['window'])->toBe(5);
});

it('dedupes and filters require-for-roles', function (): void {
    $plugin = (new TwoFactorPlugin)->requireForRoles(['admin', 'admin', '', 'super_admin']);

    expect($plugin->getOverrides()['require_for_roles'])->toBe(['admin', 'super_admin']);
});

it('applies overrides to the settings singleton', function (): void {
    $plugin = (new TwoFactorPlugin)
        ->enable()
        ->issuer('Acme')
        ->acceptanceWindow(2)
        ->requireForRoles(['admin']);

    $plugin->apply();

    $settings = app(TwoFactorSettings::class);
    expect($settings->issuer)->toBe('Acme');
    expect($settings->window)->toBe(2);
    expect($settings->require_for_roles)->toBe(['admin']);
});

it('does not apply overrides when not enabled', function (): void {
    $plugin = (new TwoFactorPlugin)->issuer('Acme');

    $plugin->apply();

    expect(app(TwoFactorSettings::class)->issuer)->toBeNull();
});
