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
 * Per-IP rate limiter for native HTTP auth routes that *aren't* served by
 * Livewire — OAuth redirect and callback endpoints, the email-verify signed
 * URL, etc. Two windows (per-minute and per-day) pulled from
 * AuthenticationSettings so operators can tune them at runtime.
 *
 * Why this is HTTP-only (and not the brute-force defense):
 * Filament/Livewire auth forms submit through /livewire/update, NOT through
 * the named routes this middleware is attached to. That endpoint bypasses
 * route-level middleware entirely. Credential-, OTP-, and token-submission
 * throttling for those flows lives in the Livewire components themselves —
 * see Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts.
 *
 * What this middleware does still cover:
 *   - OAuth redirect/callback (GET) — every hit triggers external API work
 *     (state generation, token exchange) and should be rate-limited.
 *   - Any future native HTTP POST/PUT/DELETE auth routes the package adds.
 *
 * All HTTP methods are throttled (including GET) because OAuth flows are
 * GET-based and abuse of those routes has a real cost. Page-load routes for
 * Livewire components (/login, /register, /forgot-password, /reset-password)
 * intentionally do NOT use this middleware — refreshing a login page should
 * never lock a user out, and the actual credential submission is throttled
 * inside the component.
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
            key: 'fpb-auth-http:m:'.$request->ip(),
            decaySeconds: 60,
            limit: $perMinute,
        );

        $this->ensureBudget(
            key: 'fpb-auth-http:d:'.$request->ip(),
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
                __('filament-panel-base::auth.throttle_rate_limited', ['seconds' => $retryAfter])
            );
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
