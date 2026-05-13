<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Contracts;

use Illuminate\Database\Eloquent\Model;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Marker contract for User models that support social (OAuth) login. The
 * default trait implementation lives in
 * {@see \Codenzia\FilamentPanelBase\Auth\Concerns\FindsOrCreatesFromSocialite}.
 */
interface SupportsSocialLogin
{
    /**
     * Find an existing user that matches the Socialite payload, or create
     * a new one. Return value is the host's User model.
     */
    public static function findOrCreateFromSocialite(string $provider, SocialiteUser $socialUser): Model;
}
