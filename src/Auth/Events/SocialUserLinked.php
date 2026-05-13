<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Fired when a Socialite identity is linked to a user, either by creating
 * a fresh user, matching by provider id, or by attaching the social ids
 * to an existing user matched by email. `linked` differentiates the first
 * link from a returning sign-in.
 */
class SocialUserLinked
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly string $provider,
        public readonly SocialiteUser $socialUser,
        public readonly bool $linked = false,
    ) {}
}
