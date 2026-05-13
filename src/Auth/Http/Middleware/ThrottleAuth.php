<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Http\Middleware;

use Closure;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-IP rate limiter for auth routes. Two windows — per-minute (default 5)
 * and per-day (default 50) — both configured through AuthenticationSettings.
 *
 * Route declaration:
 *   Route::middleware(['web', ThrottleAuth::class])->group(...)
 */
class ThrottleAuth
{
    public function __construct(
        private readonly AuthenticationSettings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $perMinute = max(1, $this->settings->throttle_per_minute);
        $perDay = max($perMinute, $this->settings->throttle_per_day);

        $this->ensureBudget(
            key: 'fpb-auth:m:'.$request->ip(),
            decaySeconds: 60,
            limit: $perMinute,
        );

        $this->ensureBudget(
            key: 'fpb-auth:d:'.$request->ip(),
            decaySeconds: 86_400,
            limit: $perDay,
        );

        return $next($request);
    }

    private function ensureBudget(string $key, int $decaySeconds, int $limit): void
    {
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new ThrottleRequestsException(
                __('panel-base::auth.throttle_rate_limited', ['seconds' => $retryAfter])
            );
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
