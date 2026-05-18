<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Contracts;

use Codenzia\FilamentPanelBase\Auth\Models\SocialAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Contract for User models that support social (OAuth) login. The default
 * trait implementation lives in
 * {@see \Codenzia\FilamentPanelBase\Auth\Concerns\FindsOrCreatesFromSocialite}.
 *
 * The contract requires three things from the host User model:
 *  1. A static find-or-create that resolves a Socialite payload to a User.
 *  2. A `socialAccounts()` HasMany relation to {@see SocialAccount}.
 *  3. A `linkSocialAccount()` instance method that attaches an authenticated
 *     user to a new provider (used by ManageSocialAccounts profile UI).
 */
interface SupportsSocialLogin
{
    /**
     * Find an existing user that matches the Socialite payload, or create
     * a new one. Returns either the resolved User model, or `null` when
     * the configured policy rejects the sign-in (e.g. an email-conflict
     * policy of `require_login`). When `null`, the caller is responsible
     * for surfacing a flash error to the user.
     */
    public static function findOrCreateFromSocialite(string $provider, SocialiteUser $socialUser): ?Model;

    /**
     * Attach a new SocialAccount to this already-authenticated user. Used
     * by the profile-page "Connect" flow. Returns the created row, or
     * throws when the provider+id pair is already linked to a different
     * user (to prevent cross-account theft).
     */
    public function linkSocialAccount(string $provider, SocialiteUser $socialUser): SocialAccount;

    /**
     * @return HasMany<SocialAccount>
     */
    public function socialAccounts(): HasMany;
}
