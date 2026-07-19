<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Livewire\Register;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * PNB-013 (email lower-cased at intake), PNB-018 (Password::defaults baseline)
 * and PNB-037 (throttle spent only on valid submissions) exercised through the
 * Register Livewire component.
 */
beforeEach(function (): void {
    $this->createUsersTable();

    config()->set('session.driver', 'array');
    config()->set('filament-panel-base.user_model', TwoFactorUser::class);

    if (! Route::has('home')) {
        Route::get('/home', fn () => 'home')->name('home');
    }

    $twoFactor = $this->settingsStub(TwoFactorSettings::class);
    $twoFactor->enabled = false;
    app()->instance(TwoFactorSettings::class, $twoFactor);

    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->credentials_mode = 'email';
    $settings->require_email_verification = false;
    $settings->require_phone_verification = false;
    $settings->throttle_per_minute = 2;
    $settings->throttle_per_day = 50;
    app()->instance(AuthenticationSettings::class, $settings);
});

it('lower-cases the email at registration so no case-variant duplicate is created (PNB-013)', function (): void {
    Livewire::test(Register::class)
        ->set('name', 'Mixed')
        ->set('email', ' Mixed@Example.COM ')
        ->set('password', 'Secret123')
        ->set('password_confirmation', 'Secret123')
        ->call('register')
        ->assertHasNoErrors();

    expect(TwoFactorUser::query()->pluck('email')->all())->toBe(['mixed@example.com']);
});

it('rejects a password below the Password::defaults() baseline (PNB-018)', function (): void {
    Livewire::test(Register::class)
        ->set('name', 'Weak')
        ->set('email', 'weak@example.com')
        ->set('password', 'abcdefgh') // letters only, no number → fails the baseline
        ->set('password_confirmation', 'abcdefgh')
        ->call('register')
        ->assertHasErrors('password');

    expect(TwoFactorUser::query()->count())->toBe(0);
});

it('does not spend the register throttle budget on validation failures (PNB-037)', function (): void {
    // Three submissions that fail validation (mismatched confirmation). With the
    // old hit-before-validate order these would exhaust the per-IP minute bucket
    // (limit 2); now they cost nothing, so a valid signup afterwards still lands.
    foreach (range(1, 3) as $i) {
        Livewire::test(Register::class)
            ->set('name', 'Bot')
            ->set('email', "bot{$i}@example.com")
            ->set('password', 'Secret123')
            ->set('password_confirmation', 'nope')
            ->call('register')
            ->assertHasErrors('password');
    }

    Livewire::test(Register::class)
        ->set('name', 'Real')
        ->set('email', 'real@example.com')
        ->set('password', 'Secret123')
        ->set('password_confirmation', 'Secret123')
        ->call('register')
        ->assertHasNoErrors();

    expect(TwoFactorUser::query()->where('email', 'real@example.com')->exists())->toBeTrue();
});
