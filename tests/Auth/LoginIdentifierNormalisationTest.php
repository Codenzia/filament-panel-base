<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Livewire\Login;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * PNB-013 / PNB-014: the login identifier must be normalised the same way
 * registration stored it — a lower-cased email and an E.164 phone number — so
 * a case-variant email or a locally-typed phone still resolves the account.
 */
beforeEach(function (): void {
    $this->createUsersTable();
    Schema::table('users', function (Blueprint $table): void {
        $table->string('phone')->nullable();
    });

    config()->set('session.driver', 'array');
    config()->set('auth.providers.users.model', TwoFactorUser::class);

    if (! Route::has('home')) {
        Route::get('/home', fn () => 'home')->name('home');
    }

    $twoFactor = $this->settingsStub(TwoFactorSettings::class);
    $twoFactor->enabled = false;
    app()->instance(TwoFactorSettings::class, $twoFactor);
});

it('logs in when the email case differs from the stored address (PNB-013)', function (): void {
    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->credentials_mode = 'email';
    app()->instance(AuthenticationSettings::class, $settings);

    TwoFactorUser::create([
        'name' => 'Case',
        'email' => 'case@example.com',
        'password' => bcrypt('secret-password'),
    ]);

    Livewire::test(Login::class)
        ->set('identifier', '  CASE@Example.COM ')
        ->set('password', 'secret-password')
        ->call('login')
        ->assertHasNoErrors();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->email)->toBe('case@example.com');
});

it('logs in with a locally-typed phone number after E.164 registration (PNB-014)', function (): void {
    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->credentials_mode = 'phone';
    $settings->default_country_code = '+962';
    app()->instance(AuthenticationSettings::class, $settings);

    TwoFactorUser::create([
        'name' => 'Phone',
        'email' => 'phone@example.com',
        'phone' => '+962791234567',
        'password' => bcrypt('secret-password'),
    ]);

    Livewire::test(Login::class)
        ->set('identifier', '0791234567')
        ->set('password', 'secret-password')
        ->call('login')
        ->assertHasNoErrors();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->phone)->toBe('+962791234567');
});
