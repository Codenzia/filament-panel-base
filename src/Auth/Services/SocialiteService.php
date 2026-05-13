<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Services;

use Codenzia\FilamentPanelBase\Auth\Contracts\SupportsSocialLogin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
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
     * @param  class-string<\Illuminate\Database\Eloquent\Model&SupportsSocialLogin>  $userModel
     */
    public function handle(string $provider, string $userModel): Model
    {
        $socialUser = Socialite::driver($provider)->user();

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $userModel::findOrCreateFromSocialite($provider, $socialUser);

        return $user;
    }
}
