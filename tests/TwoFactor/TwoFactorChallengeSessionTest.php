<?php

use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorChallengeSession;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Support\Facades\Cookie;

/** The private cookie name the service reads/writes. */
function rememberCookieName(): string
{
    return (new ReflectionClass(TwoFactorChallengeSession::class))->getConstant('REMEMBER_COOKIE');
}

/** Compute the server-side HMAC part of a remember-device cookie. */
function deviceHmac(TwoFactorChallengeSession $challenge, $user, string $issuedAt, string $rand): string
{
    $m = new ReflectionMethod(TwoFactorChallengeSession::class, 'deviceToken');
    $m->setAccessible(true);

    return $m->invoke($challenge, $user, $issuedAt, $rand);
}

/** Put a raw remember-device cookie onto the current request. */
function setRememberCookie(string $value): void
{
    request()->cookies->set(rememberCookieName(), $value);
}

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

    $reflection = new ReflectionMethod(TwoFactorChallengeSession::class, 'deviceToken');
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

    $reflection = new ReflectionMethod(TwoFactorChallengeSession::class, 'deviceToken');
    $reflection->setAccessible(true);
    $tokenA = $reflection->invoke($this->challenge, $this->user);

    $this->user->generateTwoFactorSecret(); // new secret
    $this->user->refresh();
    $tokenB = $reflection->invoke($this->challenge, $this->user);

    expect($tokenA)->not->toBe($tokenB);
});

it('accepts a current-format remember-device cookie (PNB-009)', function (): void {
    $this->user->generateTwoFactorSecret();
    $this->user->refresh();

    $issuedAt = (string) now()->getTimestamp();
    $rand = 'device-rand-aaaaaaaaaaaa';
    setRememberCookie($issuedAt.'.'.$rand.'.'.deviceHmac($this->challenge, $this->user, $issuedAt, $rand));

    expect($this->challenge->deviceIsRemembered($this->user))->toBeTrue();
});

it('fails closed on a legacy bare-HMAC cookie (PNB-009)', function (): void {
    $this->user->generateTwoFactorSecret();
    $this->user->refresh();

    // Old format: a single HMAC with no issuedAt/rand prefix. explode() yields
    // one part, so the new verifier rejects it and re-challenges once.
    setRememberCookie(deviceHmac($this->challenge, $this->user, '', ''));

    expect($this->challenge->deviceIsRemembered($this->user))->toBeFalse();
});

it('rejects a remember-device cookie older than the server-side TTL (PNB-009)', function (): void {
    $this->user->generateTwoFactorSecret();
    $this->user->refresh();

    // Issued 31 days ago — past the 30-day TTL — but with an otherwise valid
    // HMAC. It must still be rejected regardless of the browser cookie lifetime.
    $issuedAt = (string) now()->subDays(31)->getTimestamp();
    $rand = 'stale-rand-bbbbbbbbbbbbb';
    setRememberCookie($issuedAt.'.'.$rand.'.'.deviceHmac($this->challenge, $this->user, $issuedAt, $rand));

    expect($this->challenge->deviceIsRemembered($this->user))->toBeFalse();
});

it('binds each cookie to per-issue entropy so two devices never share a value (PNB-009)', function (): void {
    $this->user->generateTwoFactorSecret();
    $this->user->refresh();

    $issuedAt = (string) now()->getTimestamp();

    // Same user, same issue-time, different random component => different HMAC.
    expect(deviceHmac($this->challenge, $this->user, $issuedAt, 'randA'))
        ->not->toBe(deviceHmac($this->challenge, $this->user, $issuedAt, 'randB'));

    // And rememberDevice() actually queues a three-part cookie, not the old
    // bare token shared across every browser.
    $this->challenge->rememberDevice($this->user, 30);
    $queued = Cookie::getQueuedCookies();
    expect($queued)->not->toBeEmpty();
    $value = $queued[0]->getValue();
    expect(explode('.', $value, 3))->toHaveCount(3);
    expect($value)->not->toBe(deviceHmac($this->challenge, $this->user, '', ''));
});

it('invalidates a remembered device once the remember-token nonce is rotated (PNB-010)', function (): void {
    $this->user->generateTwoFactorSecret();
    $this->user->refresh();

    $issuedAt = (string) now()->getTimestamp();
    $rand = 'rotate-rand-ccccccccccccc';
    setRememberCookie($issuedAt.'.'.$rand.'.'.deviceHmac($this->challenge, $this->user, $issuedAt, $rand));

    // Baseline: the device is trusted.
    expect($this->challenge->deviceIsRemembered($this->user))->toBeTrue();

    // A password reset / profile credential change rotates the nonce. The old
    // cookie's HMAC no longer matches, so the device is re-challenged.
    $this->user->rotateTwoFactorRememberToken();
    $this->user->refresh();

    expect($this->challenge->deviceIsRemembered($this->user))->toBeFalse();
});
