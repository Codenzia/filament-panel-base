<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Sessions\Livewire\DeviceSessionList;
use Codenzia\FilamentPanelBase\Sessions\Settings\SessionManagementSettings;
use Codenzia\FilamentPanelBase\Tests\Support\FilamentIconStub;
use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * PNB-025: revoking another device (and "sign out everywhere else") is a
 * privileged action and must re-confirm the current password before it runs,
 * mirroring the 2FA-disable action.
 */
beforeEach(function (): void {
    $this->createUsersTable();
    $this->createSessionsTable();

    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');

    // Filament's Blade components aren't registered in the package test env, so
    // stub the <x-filament::icon> the device-session view renders.
    Blade::component(FilamentIconStub::class, 'filament::icon');

    $settings = $this->settingsStub(SessionManagementSettings::class);
    $settings->idle_threshold_minutes = 60;
    $settings->allow_logout_other_devices = true;
    app()->instance(SessionManagementSettings::class, $settings);

    $this->user = TwoFactorUser::create([
        'name' => 'Ivy',
        'email' => 'ivy@example.com',
        'password' => bcrypt('secret-password'),
    ]);
    $this->actingAs($this->user);

    DB::table('sessions')->insert([
        'id' => 'other-device',
        'user_id' => $this->user->getKey(),
        'ip_address' => '1.1.1.1',
        'user_agent' => 'Mozilla',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);
});

it('opens a password prompt instead of revoking immediately (PNB-025)', function (): void {
    Livewire::test(DeviceSessionList::class)
        ->call('promptRevoke', 'other-device')
        ->assertSet('confirmingAction', true)
        ->assertSet('pendingAction', 'revoke')
        ->assertSet('pendingSessionId', 'other-device');

    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeTrue();
});

it('does not revoke another device when the password is wrong (PNB-025)', function (): void {
    Livewire::test(DeviceSessionList::class)
        ->call('promptRevoke', 'other-device')
        ->set('password', 'wrong-password')
        ->call('confirmAction')
        ->assertHasErrors('password');

    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeTrue();
});

it('revokes another device once the correct password is confirmed (PNB-025)', function (): void {
    Livewire::test(DeviceSessionList::class)
        ->call('promptRevoke', 'other-device')
        ->set('password', 'secret-password')
        ->call('confirmAction')
        ->assertHasNoErrors();

    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse();
});

it('requires the password before signing out every other device (PNB-025)', function (): void {
    DB::table('sessions')->insert([
        'id' => 'third-device',
        'user_id' => $this->user->getKey(),
        'ip_address' => '2.2.2.2',
        'user_agent' => 'Mozilla',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    Livewire::test(DeviceSessionList::class)
        ->call('promptLogoutOtherDevices')
        ->assertSet('confirmingAction', true)
        ->assertSet('pendingAction', 'logout-others')
        ->set('password', 'wrong-password')
        ->call('confirmAction')
        ->assertHasErrors('password');

    // Both other devices survive a failed confirmation.
    expect(DB::table('sessions')->whereIn('id', ['other-device', 'third-device'])->count())->toBe(2);
});
