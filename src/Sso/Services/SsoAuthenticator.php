<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sso\Services;

use App\Models\User;
use Codenzia\FilamentPanelBase\Auth\Rules\AllowedEmailDomain;
use Codenzia\FilamentPanelBase\Auth\Services\RegistrationPipeline;
use Codenzia\FilamentPanelBase\Auth\Support\UnusablePassword;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Codenzia\FilamentPanelBase\Sso\Exceptions\SsoException;
use Codenzia\FilamentPanelBase\Sso\Models\SsoIdentity;
use Codenzia\FilamentPanelBase\Sso\Support\ProviderConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Turns verified id_token claims into a local user.
 *
 * Resolution order:
 *   1. `sso_identities` row for (provider, issuer, subject) — the stable path.
 *      The subject is what the provider guarantees is immutable, so a renamed
 *      or re-addressed account still lands on the same user. The issuer is
 *      part of the key because a provider config entry can be re-pointed at a
 *      different IdP, and the same subject string at the new issuer is a
 *      different person.
 *   2. Match on the email claim — but only when the local account has already
 *      proven ownership of that address. An unverified local row is never
 *      linked automatically (see `resolve()`).
 *   3. Auto-provision through the ordinary registration pipeline, and only
 *      when the host explicitly turned it on.
 *
 * SSO is strictly additive: nothing here touches an existing user's password,
 * so an account can keep signing in the ordinary way.
 */
class SsoAuthenticator
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function resolve(ProviderConfig $provider, array $claims): Model
    {
        $subject = (string) ($claims['sub'] ?? '');

        if ($subject === '') {
            throw SsoException::make(
                'filament-panel-base::auth.sso_provider_error',
                ['provider' => $provider->label],
                'claims carried no sub',
            );
        }

        // The verifier has already pinned this against the discovery document,
        // so it is the trusted issuer identity rather than an attacker string.
        $issuer = (string) ($claims['iss'] ?? '');

        if ($issuer === '') {
            throw SsoException::make(
                'filament-panel-base::auth.sso_provider_error',
                ['provider' => $provider->label],
                'claims carried no iss',
            );
        }

        $identity = SsoIdentity::query()
            ->where('provider', $provider->key)
            ->where('issuer', $issuer)
            ->where('subject', $subject)
            ->first();

        if ($identity instanceof SsoIdentity) {
            $user = $this->userQuery()->find($identity->user_id);

            if ($user instanceof Model) {
                $identity->forceFill(['last_login_at' => now()])->save();

                return $user;
            }

            // The user was deleted out from under the link — drop the orphan
            // so the unique (provider, issuer, subject) index cannot block a
            // re-link.
            $identity->delete();
        }

        $email = $this->email($claims);

        if ($email === '') {
            throw SsoException::make(
                'filament-panel-base::auth.sso_missing_email',
                ['provider' => $provider->label],
                'claims carried no email',
            );
        }

        if (! $provider->allowUnverifiedEmail && ! $this->emailIsVerified($claims)) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_unverified_email',
                ['provider' => $provider->label],
                'email_verified claim was absent or false',
            );
        }

        $user = $this->userQuery()->where('email', $email)->first();

        if ($user instanceof Model) {
            // Linking a provider identity into a *pre-existing* local account
            // requires that account to have proven ownership of the address.
            // Otherwise an attacker who pre-registers victim@x.com locally
            // (unverified, attacker's password) inherits the victim's SSO
            // session while keeping a password the victim never chose. This is
            // the same rule the Socialite path applies (PNB-003).
            if ($user->getAttribute('email_verified_at') === null) {
                throw SsoException::make(
                    'filament-panel-base::auth.sso_link_unverified_account',
                    ['provider' => $provider->label],
                    'local account matching the email claim is unverified',
                );
            }
        } else {
            $user = $this->provision($provider, $claims, $email);
        }

        $this->link($provider, $issuer, $subject, $user);

        return $user;
    }

    /**
     * Create the local account for a first-time SSO user. Only reachable when
     * `sso.auto_provision` is on — otherwise an unknown address is an error,
     * not an invitation.
     *
     * Provisioning runs through the ordinary RegistrationPipeline so an IdP
     * sign-in cannot walk around the host's admission policy: the cancellable
     * UserRegistering hook (invitations, spam listeners) still fires, and the
     * moderation status still comes from `registration_mode`. A provider only
     * skips moderation when its own `auto_approve` flag says so.
     *
     * @param  array<string, mixed>  $claims
     */
    private function provision(ProviderConfig $provider, array $claims, string $email): Model
    {
        if (! (bool) config('filament-panel-base.sso.auto_provision', false)) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_no_account',
                ['provider' => $provider->label],
                'no local user matched and auto_provision is off',
            );
        }

        $this->assertDomainAllowed($provider, $email);

        $userModel = $this->userModel();

        $attributes = [
            'name' => $this->name($claims, $email),
            'email' => $email,
            // Only an actually-verified claim marks the address verified. A
            // provider that is merely *allowed* to send unverified emails must
            // not satisfy the host's verified-email middleware.
            'email_verified_at' => $this->emailIsVerified($claims) ? now() : null,
            // Explicitly no password rather than a random hash: the column is
            // commonly NOT NULL, and a usable-looking value would make
            // "disconnect your last provider" think the user has a password
            // they were never given.
            'password' => UnusablePassword::make(),
        ];

        // An enterprise IdP may legitimately be treated as the admission
        // decision, but that is an explicit per-provider opt-in — never the
        // default on a moderated application.
        if ($provider->autoApprove && is_a($userModel, HasModerationStatus::class, true)) {
            $attributes['status'] = 'approved';
        }

        try {
            /** @var Model $user */
            $user = app(RegistrationPipeline::class)->register(
                $userModel,
                $attributes,
                ['channel' => 'sso', 'provider' => $provider->key],
            );
        } catch (RuntimeException $e) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_registration_declined',
                ['provider' => $provider->label],
                'registration was declined for the provisioned account: '.$e::class,
            );
        }

        $this->assignDefaultRole($user);

        return $user;
    }

    /**
     * Apply the host's registration domain allowlist to the claimed address.
     */
    private function assertDomainAllowed(ProviderConfig $provider, string $email): void
    {
        $allowed = true;

        (new AllowedEmailDomain)->validate(
            'email',
            $email,
            function () use (&$allowed): void {
                $allowed = false;
            },
        );

        if (! $allowed) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_domain_not_allowed',
                ['provider' => $provider->label],
                'email claim is outside the registration domain allowlist',
            );
        }
    }

    /**
     * Assign the configured default role. Silently skipped when no role is
     * configured or the host's user model has no role system — a missing role
     * package must not break the sign-in.
     */
    private function assignDefaultRole(Model $user): void
    {
        $role = config('filament-panel-base.sso.default_role');

        if (! is_string($role) || $role === '' || ! method_exists($user, 'assignRole')) {
            return;
        }

        try {
            $user->assignRole($role);
        } catch (Throwable $e) {
            Log::warning('SSO default role could not be assigned', [
                'role' => $role,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * Record (or refresh) the provider identity for this user.
     */
    private function link(ProviderConfig $provider, string $issuer, string $subject, Model $user): void
    {
        SsoIdentity::query()->updateOrCreate(
            ['provider' => $provider->key, 'issuer' => $issuer, 'subject' => $subject],
            ['user_id' => $user->getKey(), 'last_login_at' => now()],
        );
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function email(array $claims): string
    {
        $email = $claims['email'] ?? null;

        // Lower-cased at intake to line up with the rest of the package, which
        // normalises the same way (case-sensitive stores would otherwise miss).
        return is_string($email) ? mb_strtolower(trim($email)) : '';
    }

    /**
     * Providers are inconsistent about the type of `email_verified` — Google
     * sends a real boolean, some send the string "true".
     *
     * @param  array<string, mixed>  $claims
     */
    private function emailIsVerified(array $claims): bool
    {
        $verified = $claims['email_verified'] ?? null;

        return $verified === true
            || $verified === 1
            || (is_string($verified) && strtolower($verified) === 'true');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function name(array $claims, string $email): string
    {
        foreach (['name', 'preferred_username'] as $claim) {
            if (is_string($claims[$claim] ?? null) && trim($claims[$claim]) !== '') {
                return trim($claims[$claim]);
            }
        }

        $given = is_string($claims['given_name'] ?? null) ? trim($claims['given_name']) : '';
        $family = is_string($claims['family_name'] ?? null) ? trim($claims['family_name']) : '';
        $full = trim($given.' '.$family);

        return $full !== '' ? $full : Str::before($email, '@');
    }

    /**
     * @return class-string<Model>
     */
    private function userModel(): string
    {
        /** @var class-string<Model> $model */
        $model = config('filament-panel-base.user_model', User::class);

        return $model;
    }

    private function userQuery(): Builder
    {
        return $this->userModel()::query();
    }
}
