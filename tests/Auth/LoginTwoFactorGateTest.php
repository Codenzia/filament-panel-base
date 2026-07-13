<?php

use Codenzia\FilamentPanelBase\Auth\Livewire\Login;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorChallengeSession;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/**
 * PNB-001 regression: a password-only submission for a 2FA-enrolled user must
 * NOT complete a real login. The old flow ran Auth::attempt() (firing the Login
 * event + new-device listener + cycling remember-me) and then Auth::logout();
 * now credentials are validated without logging in, and Auth::login() only runs
 * after the 2FA gate.
 */
beforeEach(function (): void {
    $this->createUsersTable();
    config()->set('session.driver', 'array');
    config()->set('auth.providers.users.model', TwoFactorUser::class);

    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->recovery_code_count = 8;
    $settings->digits = 6;
    $settings->period = 30;
    $settings->window = 1;
    app()->instance(TwoFactorSettings::class, $settings);

    $authSettings = $this->settingsStub(AuthenticationSettings::class);
    $authSettings->credentials_mode = 'email';
    $authSettings->throttle_per_minute = 5;
    $authSettings->throttle_per_day = 50;
    app()->instance(AuthenticationSettings::class, $authSettings);

    $this->user = TwoFactorUser::create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => bcrypt('secret-password'),
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_confirmed_at' => now(),
    ]);
});

it('does not fire a Login event or authenticate on a 2FA-pending password submission', function (): void {
    Event::fake([LoginEvent::class]);

    Livewire::test(Login::class)
        ->set('identifier', 'bob@example.com')
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect(route('two-factor.challenge'));

    // The password was correct, but the user must NOT be logged in yet…
    expect(Auth::check())->toBeFalse();
    // …and the premature Login event must never have fired.
    Event::assertNotDispatched(LoginEvent::class);
    // …while the pending user is stashed for the challenge step.
    expect(app(TwoFactorChallengeSession::class)->hasPending())->toBeTrue();
});

it('rejects a wrong password without authenticating', function (): void {
    Livewire::test(Login::class)
        ->set('identifier', 'bob@example.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('identifier');

    expect(Auth::check())->toBeFalse();
});
