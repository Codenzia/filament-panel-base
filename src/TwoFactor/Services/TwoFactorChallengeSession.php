<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Services;

use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Stashes the intermediate "passed credentials, awaiting 2FA" state in
 * the session. Mirrors Laravel Fortify's `login.id` / `login.remember`
 * convention so anyone familiar with Fortify can predict the shape.
 *
 * Important: never call Auth::login() before stashing — keeping the
 * user logged-out until the second factor passes is the entire point.
 */
class TwoFactorChallengeSession
{
    private const SESSION_KEY = 'codenzia.two_factor_challenge';

    private const REMEMBER_COOKIE = 'codenzia_2fa_remember';

    /** Hard ceiling on the remember-device cookie lifetime, in days. */
    private const MAX_REMEMBER_DAYS = 365;

    /**
     * Stash the user pending 2FA verification. Caller must NOT also call
     * Auth::login() — that defeats the gate.
     */
    public function stash(Authenticatable $user, bool $remember = false): void
    {
        session()->put(self::SESSION_KEY, [
            'id' => $user->getAuthIdentifier(),
            'remember' => $remember,
            'intended' => session('url.intended'),
        ]);
    }

    public function hasPending(): bool
    {
        return session()->has(self::SESSION_KEY.'.id');
    }

    /**
     * Pull the pending user from the session (without clearing it). The
     * caller is expected to call `forget()` once the challenge passes.
     */
    public function pendingUser(): ?Authenticatable
    {
        $id = session(self::SESSION_KEY.'.id');

        if ($id === null) {
            return null;
        }

        $provider = Auth::guard()->getProvider();

        return $provider->retrieveById($id);
    }

    public function pendingRemember(): bool
    {
        return (bool) session(self::SESSION_KEY.'.remember', false);
    }

    public function pendingIntendedUrl(): ?string
    {
        $url = session(self::SESSION_KEY.'.intended');

        return is_string($url) ? $url : null;
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Issue a long-lived "remember this device, skip 2FA next time" cookie.
     *
     * Value is `issuedAt.rand.hmac(userId|secret|nonce|issuedAt|rand)`:
     *   - `issuedAt` is a server-checked issue time so the cookie can be
     *     rejected past a TTL even if the browser keeps sending it.
     *   - `rand` is a per-issue random component so no two devices ever hold
     *     the same value (the old cookie was identical across all of a user's
     *     browsers).
     * It is still verifiable server-side without a DB lookup.
     */
    public function rememberDevice(Authenticatable $user, int $days): void
    {
        $days = max(1, min($days, self::MAX_REMEMBER_DAYS));

        $issuedAt = (string) now()->getTimestamp();
        $rand = Str::random(24);

        Cookie::queue(
            self::REMEMBER_COOKIE,
            $issuedAt.'.'.$rand.'.'.$this->deviceToken($user, $issuedAt, $rand),
            $days * 24 * 60,
            null,
            null,
            request()->secure(),
            true,
        );
    }

    public function deviceIsRemembered(Authenticatable $user): bool
    {
        $cookie = request()->cookie(self::REMEMBER_COOKIE);

        if (! is_string($cookie) || $cookie === '') {
            return false;
        }

        // New format: issuedAt.rand.hmac. Legacy (bare-HMAC) cookies split
        // into a single part and simply fail closed here — the user is
        // re-challenged once and re-issued a current-format cookie.
        $parts = explode('.', $cookie, 3);

        if (count($parts) !== 3) {
            return false;
        }

        [$issuedAt, $rand, $hmac] = $parts;

        if (! ctype_digit($issuedAt) || $rand === '' || $hmac === '') {
            return false;
        }

        // Server-side expiry: reject past the configured TTL regardless of the
        // browser-side cookie lifetime.
        $ageSeconds = now()->getTimestamp() - (int) $issuedAt;

        if ($ageSeconds < 0 || $ageSeconds > $this->rememberDeviceTtlDays() * 86_400) {
            return false;
        }

        return hash_equals($this->deviceToken($user, $issuedAt, $rand), $hmac);
    }

    public function forgetDevice(): void
    {
        Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE));
    }

    /**
     * Hash combines the user identifier with the raw secret, a rotatable
     * server-side nonce, the cookie issue-time, a per-issue random component,
     * and APP_KEY. Disabling 2FA (or regenerating the secret) invalidates every
     * remember-device cookie automatically; the nonce additionally lets "log
     * out everywhere" / device revoke kill all outstanding cookies without
     * changing the secret. `$issuedAt`/`$rand` bind the HMAC to one specific
     * cookie so it cannot be forged or shared across devices.
     */
    private function deviceToken(Authenticatable $user, string $issuedAt = '', string $rand = ''): string
    {
        $secret = method_exists($user, 'getRawOriginal')
            ? (string) $user->getRawOriginal('two_factor_secret')
            : '';

        $nonce = method_exists($user, 'twoFactorRememberToken')
            ? $user->twoFactorRememberToken()
            : '';

        return hash_hmac(
            'sha256',
            (string) $user->getAuthIdentifier().'|'.$secret.'|'.$nonce.'|'.$issuedAt.'|'.$rand,
            (string) config('app.key'),
        );
    }

    /**
     * Configured server-side TTL (days) for remember-device cookies. Prefers
     * the admin-editable setting; falls back to the DB-free config default so
     * the check still works when the settings table is unavailable.
     */
    private function rememberDeviceTtlDays(): int
    {
        try {
            $days = app(TwoFactorSettings::class)->remember_device_days;
        } catch (\Throwable) {
            $days = (int) config('filament-panel-base.two_factor.remember_device_days', 30);
        }

        return max(1, min($days, self::MAX_REMEMBER_DAYS));
    }
}
