<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Events;

use Codenzia\FilamentPanelBase\Auth\Concerns\FindsOrCreatesFromSocialite;
use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Pre-persistence hook fired by the default {@see FindsOrCreatesFromSocialite}
 * trait just before a new User row and/or SocialAccount row are written.
 *
 * Subscribers can mutate `$userAttributes` and `$socialAccountAttributes`
 * to customise what gets persisted — for example, mapping the provider's
 * avatar/locale onto the User model, or pulling a department code out of
 * the Socialite payload onto the SocialAccount row.
 *
 * `$userAttributes` is only persisted when a new user is being created.
 * For returning sign-ins (provider+id already known) only the social row
 * may be refreshed, so user-level mutations are ignored on that path.
 *
 * Fired BEFORE persistence; the post-persistence counterpart is
 * {@see SocialUserLinked}.
 */
class SocialAccountMapping
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $socialAccountAttributes
     */
    public function __construct(
        public array $userAttributes,
        public array $socialAccountAttributes,
        public readonly string $provider,
        public readonly SocialiteUser $socialUser,
        public readonly bool $creatingUser,
    ) {}
}
