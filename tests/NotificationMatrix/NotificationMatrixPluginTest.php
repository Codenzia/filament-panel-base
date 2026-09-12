<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
use Codenzia\FilamentPanelBase\NotificationMatrix\Filament\Pages\ManageNotificationPreferences;
use Codenzia\FilamentPanelBase\NotificationMatrix\NotificationPreferences;
use Codenzia\FilamentPanelBase\Tests\Support\TestUser;
use Filament\Panel;

afterEach(function (): void {
    ManageNotificationPreferences::$authorizeUsing = null;
});

it('is opt-in: the page stays off until withNotificationPreferencesPage() is called', function (): void {
    expect(FilamentPanelBasePlugin::make()->hasNotificationPreferencesPage())->toBeFalse();
    expect(FilamentPanelBasePlugin::make()->withNotificationPreferencesPage()->hasNotificationPreferencesPage())->toBeTrue();
});

it('registers the page on the panel when opted in and the module is enabled', function (): void {
    config(['filament-panel-base.notification-matrix.enabled' => true]);

    $plugin = FilamentPanelBasePlugin::make()->withNotificationPreferencesPage();
    $panel = Panel::make()->id('nm-enabled-test');
    $plugin->register($panel);

    expect($panel->getPages())->toContain(ManageNotificationPreferences::class);
});

it('never registers the page when the module config is disabled, even if opted in', function (): void {
    config(['filament-panel-base.notification-matrix.enabled' => false]);

    $plugin = FilamentPanelBasePlugin::make()->withNotificationPreferencesPage();
    $panel = Panel::make()->id('nm-disabled-test');
    $plugin->register($panel);

    expect($panel->getPages())->not->toContain(ManageNotificationPreferences::class);
});

it('never registers the page when the host never opted in, even if the module config is enabled', function (): void {
    config(['filament-panel-base.notification-matrix.enabled' => true]);

    $plugin = FilamentPanelBasePlugin::make();
    $panel = Panel::make()->id('nm-not-opted-in-test');
    $plugin->register($panel);

    expect($panel->getPages())->not->toContain(ManageNotificationPreferences::class);
});

it('while the module is disabled, allows() always returns true regardless of stored preferences', function (): void {
    config(['filament-panel-base.notification-matrix.enabled' => false]);

    $this->createUsersTable();
    $user = TestUser::create(['email' => 'a@b.com', 'password' => 'x']);

    // No notification_preferences table exists at all while disabled — the
    // seam must not even attempt to query it.
    expect(NotificationPreferences::allows($user, 'anything.at.all', 'mail'))->toBeTrue();
    expect(NotificationPreferences::allows($user, 'anything.at.all', 'database'))->toBeTrue();
});
