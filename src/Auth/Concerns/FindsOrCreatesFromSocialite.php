<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Concerns;

use Codenzia\FilamentPanelBase\Auth\Events\SocialUserLinked;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Default implementation of
 * {@see \Codenzia\FilamentPanelBase\Auth\Contracts\SupportsSocialLogin}.
 *
 * Resolution order:
 *   1. Match by `provider` + `provider_id` (deterministic).
 *   2. Match by email — link the social identity to the existing account.
 *   3. Create a new user with a random password, mark email verified
 *      (the provider already verified it), and persist the social identity.
 *
 * Apps that need a different shape can override `findOrCreateFromSocialite`
 * directly on their User model.
 */
trait FindsOrCreatesFromSocialite
{
    public static function findOrCreateFromSocialite(string $provider, SocialiteUser $socialUser): Model
    {
        $email = $socialUser->getEmail();
        $providerId = (string) $socialUser->getId();

        /** @var static|null $user */
        $user = static::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($user instanceof static) {
            event(new SocialUserLinked($user, $provider, $socialUser, linked: false));

            return $user;
        }

        if ($email) {
            /** @var static|null $byEmail */
            $byEmail = static::query()->where('email', $email)->first();

            if ($byEmail instanceof static) {
                $byEmail->forceFill([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                ])->save();

                event(new SocialUserLinked($byEmail, $provider, $socialUser, linked: true));

                return $byEmail;
            }
        }

        $user = static::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? Str::before((string) $email, '@'),
            'email' => $email,
            'email_verified_at' => $email ? now() : null,
            'password' => bcrypt(Str::random(40)),
            'provider' => $provider,
            'provider_id' => $providerId,
        ]);

        event(new SocialUserLinked($user, $provider, $socialUser, linked: true));

        return $user;
    }
}
