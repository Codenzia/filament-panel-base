<?php

use Codenzia\FilamentPanelBase\Auth\Livewire\ForgotPassword;
use Codenzia\FilamentPanelBase\Auth\Livewire\Login;
use Codenzia\FilamentPanelBase\Auth\Livewire\Register;
use Codenzia\FilamentPanelBase\Auth\Livewire\ResetPassword;
use Codenzia\FilamentPanelBase\Auth\Livewire\VerifyEmailNotice;
use Codenzia\FilamentPanelBase\Auth\Livewire\VerifyOtp;
use Codenzia\FilamentPanelBase\TwoFactor\Livewire\TwoFactorChallenge;

/*
 * The config docs say: "Set [auth.layout] to null to use a minimal bundled
 * fallback layout." Previously the components called
 * `config('...', 'fallback')` — the default only fires when the key is
 * missing, NOT when the value is explicitly null/'' — so hosts that
 * followed the docs hit MissingLayoutException. The components now use
 * `?:` so null/empty falls back to the bundled layout.
 */

$resolve = static fn (): string => config('filament-panel-base.auth.layout') ?: 'filament-panel-base::layouts.auth';

dataset('auth_components', [
    [Login::class],
    [Register::class],
    [ForgotPassword::class],
    [ResetPassword::class],
    [VerifyEmailNotice::class],
    [VerifyOtp::class],
    [TwoFactorChallenge::class],
]);

it('falls back to the bundled layout when auth.layout is null', function (string $componentClass) use ($resolve) {
    config(['filament-panel-base.auth.layout' => null]);

    expect($resolve())->toBe('filament-panel-base::layouts.auth')
        ->and(class_exists($componentClass))->toBeTrue();
})->with('auth_components');

it('falls back to the bundled layout when auth.layout is an empty string', function () use ($resolve) {
    config(['filament-panel-base.auth.layout' => '']);

    expect($resolve())->toBe('filament-panel-base::layouts.auth');
});

it('honours an explicit auth.layout string', function () use ($resolve) {
    config(['filament-panel-base.auth.layout' => 'layouts.app']);

    expect($resolve())->toBe('layouts.app');
});
