<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Http\Middleware\ThrottleAuth;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Directly exercises the per-IP HTTP throttle applied to native auth routes
 * (OAuth redirect/callback). ThrottlesAuthAttemptsTest covers the Livewire
 * trait; this covers the middleware.
 *
 * @see ThrottleAuth
 */
function makeThrottleAuth(int $perMinute): ThrottleAuth
{
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    $settings->throttle_per_minute = $perMinute;
    $settings->throttle_per_day = 1000;

    return new ThrottleAuth($settings);
}

it('allows requests up to the per-minute budget then throttles', function (): void {
    RateLimiter::clear('fpb-auth-http:m:10.0.0.9');
    RateLimiter::clear('fpb-auth-http:d:10.0.0.9');

    $middleware = makeThrottleAuth(3);
    $request = Request::create('/oauth/google/redirect', 'GET', server: ['REMOTE_ADDR' => '10.0.0.9']);
    $next = fn (): Response => new Response('ok');

    // First 3 pass.
    for ($i = 0; $i < 3; $i++) {
        expect($middleware->handle($request, $next)->getContent())->toBe('ok');
    }

    // 4th is over budget.
    expect(fn () => $middleware->handle($request, $next))->toThrow(ThrottleRequestsException::class);
});

it('keys the budget per IP so a different IP is unaffected', function (): void {
    RateLimiter::clear('fpb-auth-http:m:10.0.0.1');
    RateLimiter::clear('fpb-auth-http:m:10.0.0.2');
    RateLimiter::clear('fpb-auth-http:d:10.0.0.1');
    RateLimiter::clear('fpb-auth-http:d:10.0.0.2');

    $middleware = makeThrottleAuth(1);
    $next = fn (): Response => new Response('ok');

    $first = Request::create('/oauth/google/redirect', 'GET', server: ['REMOTE_ADDR' => '10.0.0.1']);
    $second = Request::create('/oauth/google/redirect', 'GET', server: ['REMOTE_ADDR' => '10.0.0.2']);

    $middleware->handle($first, $next);
    expect(fn () => $middleware->handle($first, $next))->toThrow(ThrottleRequestsException::class);

    // Fresh IP still has its full budget.
    expect($middleware->handle($second, $next)->getContent())->toBe('ok');
});
