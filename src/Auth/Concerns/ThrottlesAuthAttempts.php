<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Concerns;

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Brute-force protection for Livewire-driven auth flows. Filament/Livewire form
 * submissions are dispatched through /livewire/update, which bypasses the named
 * auth routes — and therefore any route-level middleware on them. The only
 * place credential-, OTP-, and token-submission rate limiting actually fires
 * is inside the component method that handles the submission. This trait
 * provides that hook with a consistent, settings-driven key strategy.
 *
 * Three buckets are checked on every attempt:
 *   - per-IP, per-minute (catches one-IP rapid-fire)
 *   - per-identifier, per-minute (catches distributed attacks against one
 *     account from many IPs — credential stuffing)
 *   - per-IP, per-day (long-window backstop)
 *
 * Both windows pull their limits from AuthenticationSettings::throttle_per_minute
 * and throttle_per_day so operators can tune them at runtime without code
 * changes. Identifiers (email, phone, user id, OTP target) are HMAC'd with the
 * app key before being used as cache keys so they never leak in cache stores.
 *
 * Usage pattern in a Livewire component:
 *
 *   $this->ensureNotRateLimited('login', $this->identifier);
 *
 *   if (! Auth::attempt(...)) {
 *       $this->hitRateLimiter('login', $this->identifier);   // count the failure
 *       $this->addError('identifier', __('...'));
 *       return;
 *   }
 *
 *   $this->clearRateLimiter('login', $this->identifier);     // success — clear specific buckets
 *
 * The per-day IP bucket is intentionally NOT cleared on success: a single
 * successful login shouldn't reset the global daily budget for that IP, or an
 * attacker who occasionally guesses correctly would get an unlimited budget.
 */
trait ThrottlesAuthAttempts
{
    /**
     * Throws a Livewire-friendly ValidationException on `$attribute` if any
     * of the per-IP, per-identifier, or per-day buckets are over budget.
     *
     * The throw populates the form error bag with the existing
     * `auth.throttle_rate_limited` translation, including a `:seconds` retry
     * hint pulled from the bucket with the longest remaining cooldown.
     */
    protected function ensureNotRateLimited(string $action, string $identifier, string $attribute = 'identifier'): void
    {
        $settings = app(AuthenticationSettings::class);
        $perMinute = max(1, $settings->throttle_per_minute);
        $perDay = max($perMinute, $settings->throttle_per_day);

        $ip = $this->requestIp();

        $buckets = [
            ['key' => $this->rateKey($action, 'm', 'ip', $ip), 'limit' => $perMinute],
            ['key' => $this->rateKey($action, 'm', 'id', $identifier), 'limit' => $perMinute],
            ['key' => $this->rateKey($action, 'd', 'ip', $ip), 'limit' => $perDay],
        ];

        foreach ($buckets as $bucket) {
            if (RateLimiter::tooManyAttempts($bucket['key'], $bucket['limit'])) {
                throw ValidationException::withMessages([
                    $attribute => __('filament-panel-base::auth.throttle_rate_limited', [
                        'seconds' => RateLimiter::availableIn($bucket['key']),
                    ]),
                ]);
            }
        }
    }

    /**
     * Record an attempt against the per-IP-minute, per-identifier-minute, and
     * per-IP-day buckets. Call this after a failed or always-counted attempt.
     */
    protected function hitRateLimiter(string $action, string $identifier): void
    {
        $ip = $this->requestIp();

        RateLimiter::hit($this->rateKey($action, 'm', 'ip', $ip), 60);
        RateLimiter::hit($this->rateKey($action, 'm', 'id', $identifier), 60);
        RateLimiter::hit($this->rateKey($action, 'd', 'ip', $ip), 86_400);
    }

    /**
     * Clear the per-minute buckets after a successful operation. The per-day
     * IP backstop is deliberately preserved (see class docblock).
     */
    protected function clearRateLimiter(string $action, string $identifier): void
    {
        $ip = $this->requestIp();

        RateLimiter::clear($this->rateKey($action, 'm', 'ip', $ip));
        RateLimiter::clear($this->rateKey($action, 'm', 'id', $identifier));
    }

    /**
     * HMAC-hashed cache key — keeps raw emails / phones / user ids out of the
     * cache store and avoids weird characters in cache key strings.
     */
    private function rateKey(string $action, string $window, string $scope, string $value): string
    {
        $digest = hash_hmac('sha256', $value, (string) config('app.key'));

        return "fpb-auth:{$action}:{$window}:{$scope}:{$digest}";
    }

    private function requestIp(): string
    {
        return (string) (request()?->ip() ?? '0.0.0.0');
    }
}
