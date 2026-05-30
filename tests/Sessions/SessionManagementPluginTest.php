<?php

use Codenzia\FilamentPanelBase\Sessions\SessionManagementPlugin;
use Codenzia\FilamentPanelBase\Sessions\Settings\SessionManagementSettings;

beforeEach(function (): void {
    $settings = $this->settingsStub(SessionManagementSettings::class);
    $settings->enabled = true;
    $settings->notify_on_new_device = true;
    $settings->idle_threshold_minutes = 30;
    $settings->allow_logout_other_devices = true;
    app()->instance(SessionManagementSettings::class, $settings);
});

it('starts disabled until enable() is called', function (): void {
    $plugin = new SessionManagementPlugin;

    expect($plugin->isEnabled())->toBeFalse();
    $plugin->enable();
    expect($plugin->isEnabled())->toBeTrue();
});

it('clamps idle threshold to a minimum of one minute', function (): void {
    $plugin = (new SessionManagementPlugin)->idleThresholdMinutes(0);

    expect($plugin->getOverrides()['idle_threshold_minutes'])->toBe(1);
});

it('applies overrides to the settings singleton when enabled', function (): void {
    (new SessionManagementPlugin)
        ->enable()
        ->notifyOnNewDevice(false)
        ->idleThresholdMinutes(15)
        ->allowLogoutOtherDevices(false)
        ->apply();

    $settings = app(SessionManagementSettings::class);
    expect($settings->notify_on_new_device)->toBeFalse();
    expect($settings->idle_threshold_minutes)->toBe(15);
    expect($settings->allow_logout_other_devices)->toBeFalse();
});

it('does not apply overrides when not enabled', function (): void {
    (new SessionManagementPlugin)
        ->idleThresholdMinutes(99)
        ->apply();

    expect(app(SessionManagementSettings::class)->idle_threshold_minutes)->toBe(30);
});
