<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Services;

use Codenzia\FilamentPanelBase\Auth\Contracts\SupportsSocialLogin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

/**
 * Thin wrapper over `laravel/socialite` so the OAuthController doesn't talk
 * to the SDK directly. Hosts that need to short-circuit (e.g. plug
 * `dutchcodingcompany/filament-socialite` for the admin panel) can bind a
 * different implementation via the container.
 */
class SocialiteService
{
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Pull the resolved Socialite user from the callback. Wrapped so tests
     * can swap the implementation without touching the Socialite facade.
     */
    public function userFromCallback(string $provider): SocialiteUser
    {
        return Socialite::driver($provider)->user();
    }

    /**
     * Convenience wrapper for the sign-in flow: resolves the Socialite user
     * and hands it to the host's `findOrCreateFromSocialite`. Returns `null`
     * when the configured policy refuses the sign-in (caller flashes errors).
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model&SupportsSocialLogin>  $userModel
     */
    public function handle(string $provider, string $userModel): ?Model
    {
        return $userModel::findOrCreateFromSocialite($provider, $this->userFromCallback($provider));
    }
}
