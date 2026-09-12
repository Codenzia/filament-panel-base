<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Services;

use Codenzia\FilamentPanelBase\TwoFactor\Concerns\HasTwoFactorAuthentication;
use Codenzia\FilamentPanelBase\TwoFactor\Exceptions\TwoFactorUnavailableException;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Throwable;

/**
 * Answers one question for every sign-in path: should this login be
 * interrupted by a TOTP challenge?
 *
 * It lives here rather than inside each controller so that the password,
 * OAuth, OIDC SSO and demo paths cannot drift apart from one another — a
 * sign-in route that forgets this check is a 2FA bypass, so there is exactly
 * one implementation of it.
 *
 * "Disabled" and "cannot be determined" are different answers. A user model
 * without the trait, or one that never enrolled, has no second factor to ask
 * for and signs in normally. But when the policy itself is unreadable (no
 * settings table, no DB) and the user *is* enrolled, this throws
 * {@see TwoFactorUnavailableException} rather than waving them through: an
 * outage must not quietly turn an enforced factor into an optional one.
 */
class LoginChallengeDecider
{
    /**
     * @throws TwoFactorUnavailableException when an enrolled user's policy cannot be read
     */
    public function shouldChallenge(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        try {
            $settings = app(TwoFactorSettings::class);

            // Settings load lazily, so the store is only actually read on the
            // first property access — that is where an unreachable settings
            // table surfaces.
            $enabled = (bool) $settings->enabled;
        } catch (Throwable $e) {
            if ($this->isEnrolled($user)) {
                throw new TwoFactorUnavailableException(
                    'Two-factor settings could not be resolved for an enrolled user.',
                    0,
                    $e,
                );
            }

            return false;
        }

        if (! $enabled) {
            return false;
        }

        if (! $this->isEnrolled($user)) {
            return false;
        }

        if ($settings->remember_device) {
            try {
                if (app(TwoFactorChallengeSession::class)->deviceIsRemembered($user)) {
                    return false;
                }
            } catch (Throwable) {
                // Cookie unreadable — fall through and challenge.
            }
        }

        return true;
    }

    /**
     * Whether this user actually holds a confirmed second factor.
     */
    private function isEnrolled(mixed $user): bool
    {
        return in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)
            && method_exists($user, 'hasTwoFactorEnabled')
            && (bool) $user->hasTwoFactorEnabled();
    }
}
