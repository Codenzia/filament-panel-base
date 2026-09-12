<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Livewire\ForgotPassword;
use Codenzia\FilamentPanelBase\Auth\Livewire\Login;
use Codenzia\FilamentPanelBase\Auth\Livewire\Register;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Livewire\Livewire;

/**
 * BVT — shallow mount smoke test.
 *
 * Mounts the guest-facing standalone Livewire auth surfaces and asserts they
 * render without error, using the minimal settings-stub harness the deep auth
 * suites also use (settings are bound in-memory; no panel is booted).
 *
 * Surfaces whose full render needs a registered Filament panel (CommandPalette
 * pulls in <x-filament::icon>) or per-request state (ResetPassword token,
 * VerifyOtp/TwoFactorChallenge session, ManageSocialAccounts/DeviceSessionList
 * authenticated user) are exercised by their own suites and are covered here
 * only by the class/view roll-call in SurfaceRollCallTest.
 */
beforeEach(function (): void {
    $this->createUsersTable();

    $auth = $this->settingsStub(AuthenticationSettings::class);
    $auth->credentials_mode = 'email';
    app()->instance(AuthenticationSettings::class, $auth);

    $twoFactor = $this->settingsStub(TwoFactorSettings::class);
    app()->instance(TwoFactorSettings::class, $twoFactor);
});

it('mounts the guest login component', function (): void {
    Livewire::test(Login::class)->assertOk();
});

it('mounts the guest register component', function (): void {
    Livewire::test(Register::class)->assertOk();
});

it('mounts the forgot-password component', function (): void {
    Livewire::test(ForgotPassword::class)->assertOk();
});
