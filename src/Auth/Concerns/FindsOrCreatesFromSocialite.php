<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Concerns;

use Codenzia\FilamentPanelBase\Auth\Contracts\SupportsSocialLogin;
use Codenzia\FilamentPanelBase\Auth\Events\SocialAccountMapping;
use Codenzia\FilamentPanelBase\Auth\Events\SocialUserLinked;
use Codenzia\FilamentPanelBase\Auth\Models\SocialAccount;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use RuntimeException;

/**
 * Default implementation of
 * {@see SupportsSocialLogin}.
 *
 * Resolution order:
 *   1. Match by `provider` + `provider_id` on the social_accounts table.
 *   2. Match by email — outcome depends on the `social_email_linking` policy:
 *        - 'require_login'  : refuse, return null (caller flashes a hint).
 *        - 'trust_verified' : link only when both sides are verified.
 *        - 'auto'           : link unconditionally (legacy, unsafe).
 *   3. Create a new user + first social_accounts row in a single transaction.
 *      Email verification only when the provider asserts it AND the host's
 *      `social_trust_verified_email` setting allows trusting that assertion.
 *
 * Apps that need a different shape can override `findOrCreateFromSocialite`
 * directly on their User model.
 *
 * @mixin Model
 */
trait FindsOrCreatesFromSocialite
{
    public static function findOrCreateFromSocialite(string $provider, SocialiteUser $socialUser): ?Model
    {
        $providerId = (string) $socialUser->getId();
        $email = $socialUser->getEmail();

        $existingLink = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existingLink instanceof SocialAccount) {
            /** @var static|null $user */
            $user = static::query()->find($existingLink->user_id);

            if ($user instanceof static) {
                static::refreshSocialAccountTokens($existingLink, $socialUser);

                event(new SocialUserLinked($user, $provider, $socialUser, linked: false));

                return $user;
            }
        }

        if (is_string($email) && $email !== '') {
            /** @var static|null $byEmail */
            $byEmail = static::query()->where('email', $email)->first();

            if ($byEmail instanceof static) {
                $policy = static::socialAuthSettings()->social_email_linking;

                $allow = match ($policy) {
                    // Even in the legacy "auto" mode, never link by a bare email
                    // match unless the provider asserts the email is verified —
                    // an unverified-email link is a documented account-takeover
                    // vector.
                    'auto' => static::providerEmailIsVerified($socialUser),
                    'trust_verified' => static::providerEmailIsVerified($socialUser)
                        && $byEmail->getAttribute('email_verified_at') !== null,
                    default => false,
                };

                if (! $allow) {
                    session()->flash(
                        'error',
                        __('filament-panel-base::auth.social_link_existing_email', [
                            'provider' => ucfirst($provider),
                        ])
                    );

                    return null;
                }

                $mapping = static::dispatchMapping(
                    userAttributes: [],
                    socialAccountAttributes: static::baseSocialAttributes($provider, $providerId, $socialUser),
                    provider: $provider,
                    socialUser: $socialUser,
                    creatingUser: false,
                );

                $byEmail->socialAccounts()->create($mapping->socialAccountAttributes);

                event(new SocialUserLinked($byEmail, $provider, $socialUser, linked: true));

                return $byEmail;
            }
        }

        if (! is_string($email) || $email === '') {
            $credentialsMode = static::socialAuthSettings()->credentials_mode;

            if ($credentialsMode === 'email' || $credentialsMode === 'both') {
                session()->flash(
                    'error',
                    __('filament-panel-base::auth.social_missing_email', [
                        'provider' => ucfirst($provider),
                    ])
                );

                return null;
            }
        }

        $trustVerified = static::socialAuthSettings()->social_trust_verified_email
            && static::providerEmailIsVerified($socialUser);

        $name = $socialUser->getName()
            ?? $socialUser->getNickname()
            ?? (is_string($email) && $email !== '' ? Str::before($email, '@') : ucfirst($provider).' user');

        $userAttributes = [
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $trustVerified && is_string($email) && $email !== '' ? now() : null,
            'password' => bcrypt(Str::random(40)),
        ];

        // Mirror RegistrationPipeline's moderation step so social sign-ups honour
        // `registration_mode` instead of silently taking the DB column default.
        // Applied before dispatchMapping so a SocialAccountMapping listener can
        // still override the status.
        if (is_a(static::class, HasModerationStatus::class, true)) {
            $userAttributes['status'] = static::socialAuthSettings()->registration_mode === 'moderated'
                ? 'pending'
                : 'approved';
        }

        $mapping = static::dispatchMapping(
            userAttributes: $userAttributes,
            socialAccountAttributes: static::baseSocialAttributes($provider, $providerId, $socialUser),
            provider: $provider,
            socialUser: $socialUser,
            creatingUser: true,
        );

        /** @var static $user */
        $user = DB::transaction(function () use ($mapping): static {
            /** @var static $created */
            $created = static::create($mapping->userAttributes);
            $created->socialAccounts()->create($mapping->socialAccountAttributes);

            return $created;
        });

        event(new Registered($user));
        event(new SocialUserLinked($user, $provider, $socialUser, linked: true));

        return $user;
    }

    public function linkSocialAccount(string $provider, SocialiteUser $socialUser): SocialAccount
    {
        $providerId = (string) $socialUser->getId();

        $existing = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existing instanceof SocialAccount && $existing->user_id !== $this->getKey()) {
            throw new RuntimeException(
                __('filament-panel-base::auth.social_link_owned_by_other', [
                    'provider' => ucfirst($provider),
                ])
            );
        }

        if ($existing instanceof SocialAccount) {
            static::refreshSocialAccountTokens($existing, $socialUser);

            return $existing;
        }

        $mapping = static::dispatchMapping(
            userAttributes: [],
            socialAccountAttributes: static::baseSocialAttributes($provider, $providerId, $socialUser),
            provider: $provider,
            socialUser: $socialUser,
            creatingUser: false,
        );

        /** @var SocialAccount $link */
        $link = $this->socialAccounts()->create($mapping->socialAccountAttributes);

        event(new SocialUserLinked($this, $provider, $socialUser, linked: true));

        return $link;
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class, 'user_id');
    }

    /**
     * @return array<string, mixed>
     */
    protected static function baseSocialAttributes(string $provider, string $providerId, SocialiteUser $socialUser): array
    {
        return [
            'provider' => $provider,
            'provider_id' => $providerId,
            'email' => $socialUser->getEmail(),
            'name' => $socialUser->getName() ?? $socialUser->getNickname(),
            'avatar' => $socialUser->getAvatar(),
            'token' => $socialUser->token ?? null,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'expires_at' => isset($socialUser->expiresIn) && is_numeric($socialUser->expiresIn)
                ? now()->addSeconds((int) $socialUser->expiresIn)
                : null,
        ];
    }

    /**
     * Some providers expose a verified-email flag on the raw payload. We only
     * trust it when the provider explicitly says so — never inferred.
     */
    protected static function providerEmailIsVerified(SocialiteUser $socialUser): bool
    {
        $raw = method_exists($socialUser, 'getRaw') ? $socialUser->getRaw() : [];

        if (! is_array($raw)) {
            return false;
        }

        return (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false);
    }

    protected static function refreshSocialAccountTokens(SocialAccount $link, SocialiteUser $socialUser): void
    {
        $updates = array_filter([
            'token' => $socialUser->token ?? null,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'avatar' => $socialUser->getAvatar(),
            'expires_at' => isset($socialUser->expiresIn) && is_numeric($socialUser->expiresIn)
                ? now()->addSeconds((int) $socialUser->expiresIn)
                : null,
        ], static fn ($value): bool => $value !== null);

        if ($updates !== []) {
            $link->forceFill($updates)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $socialAccountAttributes
     */
    protected static function dispatchMapping(
        array $userAttributes,
        array $socialAccountAttributes,
        string $provider,
        SocialiteUser $socialUser,
        bool $creatingUser,
    ): SocialAccountMapping {
        $event = new SocialAccountMapping(
            userAttributes: $userAttributes,
            socialAccountAttributes: $socialAccountAttributes,
            provider: $provider,
            socialUser: $socialUser,
            creatingUser: $creatingUser,
        );

        event($event);

        return $event;
    }

    protected static function socialAuthSettings(): AuthenticationSettings
    {
        return app(AuthenticationSettings::class);
    }
}
