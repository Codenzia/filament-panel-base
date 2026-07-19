<?php

use Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Test fixture: a plain object that just exposes the trait's protected API
 * so we can drive it without spinning up a Livewire component runtime.
 */
class ThrottlesAuthAttemptsFixture
{
    use ThrottlesAuthAttempts {
        ensureNotRateLimited as public;
        hitRateLimiter as public;
        clearRateLimiter as public;
    }
}

beforeEach(function (): void {
    // Bind a settings stub with tight limits so the assertions are cheap.
    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->throttle_per_minute = 3;
    $settings->throttle_per_day = 10;
    app()->instance(AuthenticationSettings::class, $settings);

    // Pin the request IP so the per-IP buckets are deterministic.
    $request = Request::create('/test', 'POST');
    $request->server->set('REMOTE_ADDR', '203.0.113.7');
    app()->instance('request', $request);

    // Cache is array by default in testbench — clear between tests anyway.
    RateLimiter::clear('whatever');
});

it('allows attempts up to the per-minute budget', function (): void {
    $fixture = new ThrottlesAuthAttemptsFixture;

    // Three failed attempts should be allowed (limit = 3); the fourth check throws.
    for ($i = 0; $i < 3; $i++) {
        $fixture->ensureNotRateLimited('login', 'user@example.com');
        $fixture->hitRateLimiter('login', 'user@example.com');
    }

    expect(fn () => $fixture->ensureNotRateLimited('login', 'user@example.com'))
        ->toThrow(ValidationException::class);
});

it('puts the throttle message on the requested error attribute', function (): void {
    $fixture = new ThrottlesAuthAttemptsFixture;

    for ($i = 0; $i < 3; $i++) {
        $fixture->hitRateLimiter('login', 'user@example.com');
    }

    try {
        $fixture->ensureNotRateLimited('login', 'user@example.com', 'email');
        expect(false)->toBeTrue('ValidationException should have been thrown');
    } catch (ValidationException $e) {
        $errors = $e->validator->errors()->toArray();
        expect($errors)->toHaveKey('email')
            ->and($errors)->not->toHaveKey('identifier');
    }
});

it('isolates buckets by action', function (): void {
    $fixture = new ThrottlesAuthAttemptsFixture;

    // Burn the login budget for this identifier.
    for ($i = 0; $i < 3; $i++) {
        $fixture->hitRateLimiter('login', 'user@example.com');
    }

    // The 'forgot' action shares neither the identifier nor IP keys with 'login'
    // — its budget should be untouched.
    expect(fn () => $fixture->ensureNotRateLimited('forgot', 'user@example.com', 'email'))
        ->not->toThrow(ValidationException::class);
});

it('blocks distributed attacks against a single identifier across many IPs', function (): void {
    $fixture = new ThrottlesAuthAttemptsFixture;

    // Hit the identifier-keyed bucket from three different IPs — should fill
    // the per-identifier minute bucket regardless of IP rotation.
    foreach (['203.0.113.7', '198.51.100.42', '192.0.2.99'] as $ip) {
        $request = Request::create('/test', 'POST');
        $request->server->set('REMOTE_ADDR', $ip);
        app()->instance('request', $request);

        $fixture->hitRateLimiter('login', 'victim@example.com');
    }

    // Now a fourth IP attempts the same identifier — the per-IP bucket on
    // this fresh IP is empty, but the per-identifier bucket is full.
    $fresh = Request::create('/test', 'POST');
    $fresh->server->set('REMOTE_ADDR', '203.0.113.250');
    app()->instance('request', $fresh);

    expect(fn () => $fixture->ensureNotRateLimited('login', 'victim@example.com', 'identifier'))
        ->toThrow(ValidationException::class);
});

it('clears per-minute buckets on success but keeps the per-day bucket', function (): void {
    $fixture = new ThrottlesAuthAttemptsFixture;

    // Burn the per-minute identifier bucket.
    for ($i = 0; $i < 3; $i++) {
        $fixture->hitRateLimiter('login', 'user@example.com');
    }

    $fixture->clearRateLimiter('login', 'user@example.com');

    // Minute buckets cleared — next attempt should be allowed.
    expect(fn () => $fixture->ensureNotRateLimited('login', 'user@example.com'))
        ->not->toThrow(ValidationException::class);

    // But the per-day IP bucket should still hold the three hits.
    // Drive the per-day limit (10) by hitting 8 more times — that's 11 total,
    // which would only exceed the daily cap if the previous hits persisted.
    for ($i = 0; $i < 8; $i++) {
        $fixture->hitRateLimiter('login', 'user@example.com');
    }

    expect(fn () => $fixture->ensureNotRateLimited('login', 'user@example.com'))
        ->toThrow(ValidationException::class);
});

it('shares one bucket across case/whitespace variants of an identifier (PNB-011)', function (): void {
    $fixture = new ThrottlesAuthAttemptsFixture;

    // Three "different" spellings of the same identifier — an attacker's trick
    // to sidestep the per-identifier limit. Normalisation (lowercase + trim)
    // means they all land in one bucket, so the third check trips the limit=3.
    $fixture->hitRateLimiter('login', 'Victim@Example.com');
    $fixture->hitRateLimiter('login', 'VICTIM@EXAMPLE.COM');
    $fixture->hitRateLimiter('login', '  victim@example.com  ');

    expect(fn () => $fixture->ensureNotRateLimited('login', 'victim@example.com', 'identifier'))
        ->toThrow(ValidationException::class);
});

it('does not leak raw identifiers into cache keys', function (): void {
    $fixture = new ThrottlesAuthAttemptsFixture;

    $secret = 'top-secret@example.com';
    $fixture->hitRateLimiter('login', $secret);

    // Probe the cache store for any key containing the raw identifier — there
    // should be none. The trait HMACs identifiers before keying them.
    $store = cache()->getStore();
    $reflection = new ReflectionClass($store);

    // For the array store, the cache lives in a private $storage property.
    if ($reflection->hasProperty('storage')) {
        $storageProp = $reflection->getProperty('storage');
        $storageProp->setAccessible(true);
        $storage = $storageProp->getValue($store);

        foreach (array_keys($storage) as $key) {
            expect($key)->not->toContain($secret);
        }
    }
});
