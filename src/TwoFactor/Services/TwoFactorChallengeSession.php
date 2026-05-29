<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

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
     * Value is `sha256(userId + secret + appKey)` so it can be verified
     * server-side without a DB lookup.
     */
    public function rememberDevice(Authenticatable $user, int $days): void
    {
        Cookie::queue(
            self::REMEMBER_COOKIE,
            $this->deviceToken($user),
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

        return hash_equals($this->deviceToken($user), $cookie);
    }

    public function forgetDevice(): void
    {
        Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE));
    }

    /**
     * Hash combines the user identifier with the raw secret and APP_KEY so
     * disabling 2FA (or regenerating the secret) invalidates every
     * remember-device cookie automatically.
     */
    private function deviceToken(Authenticatable $user): string
    {
        $secret = method_exists($user, 'getRawOriginal')
            ? (string) $user->getRawOriginal('two_factor_secret')
            : '';

        return hash_hmac(
            'sha256',
            (string) $user->getAuthIdentifier().'|'.$secret,
            (string) config('app.key'),
        );
    }
}
