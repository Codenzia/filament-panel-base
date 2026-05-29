<?php

use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorChallengeSession;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;

beforeEach(function (): void {
    $this->createUsersTable();
    $this->challenge = new TwoFactorChallengeSession;

    // Configure the session driver for tests that touch session()
    config()->set('session.driver', 'array');
    config()->set('auth.providers.users.model', TwoFactorUser::class);

    // Bind a settings stub so the trait's generateTwoFactorSecret() doesn't
    // try to read the (missing) settings table.
    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->recovery_code_count = 8;
    $settings->digits = 6;
    $settings->period = 30;
    $settings->window = 1;
    app()->instance(TwoFactorSettings::class, $settings);

    $this->user = TwoFactorUser::create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'x',
    ]);
});

it('reports no pending challenge initially', function (): void {
    expect($this->challenge->hasPending())->toBeFalse();
    expect($this->challenge->pendingUser())->toBeNull();
});

it('stashes a pending user with remember flag', function (): void {
    session()->put('url.intended', 'https://example.com/admin');

    $this->challenge->stash($this->user, remember: true);

    expect($this->challenge->hasPending())->toBeTrue();
    expect($this->challenge->pendingRemember())->toBeTrue();
    expect($this->challenge->pendingIntendedUrl())->toBe('https://example.com/admin');

    $pending = $this->challenge->pendingUser();
    expect($pending)->not->toBeNull();
    expect($pending->getAuthIdentifier())->toBe($this->user->getAuthIdentifier());
});

it('forgets the pending state', function (): void {
    $this->challenge->stash($this->user);
    $this->challenge->forget();

    expect($this->challenge->hasPending())->toBeFalse();
});

it('issues and verifies a remember-device token', function (): void {
    $this->user->generateTwoFactorSecret();
    $this->user->refresh();

    $reflection = new \ReflectionMethod(TwoFactorChallengeSession::class, 'deviceToken');
    $reflection->setAccessible(true);
    $value = $reflection->invoke($this->challenge, $this->user);

    expect($value)->toBeString();
    expect(strlen($value))->toBe(64); // sha256 hex
});

it('reports device-not-remembered when no cookie is present', function (): void {
    expect($this->challenge->deviceIsRemembered($this->user))->toBeFalse();
});

it('regenerates the device token when the secret changes', function (): void {
    $this->user->generateTwoFactorSecret();
    $this->user->refresh();

    $reflection = new \ReflectionMethod(TwoFactorChallengeSession::class, 'deviceToken');
    $reflection->setAccessible(true);
    $tokenA = $reflection->invoke($this->challenge, $this->user);

    $this->user->generateTwoFactorSecret(); // new secret
    $this->user->refresh();
    $tokenB = $reflection->invoke($this->challenge, $this->user);

    expect($tokenA)->not->toBe($tokenB);
});
