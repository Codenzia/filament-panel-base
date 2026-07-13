<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Codenzia\FilamentPanelBase\TwoFactor\Concerns\HasTwoFactorAuthentication;
use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorChallengeSession;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Front-of-site Livewire login page. Reads AuthenticationSettings::credentials_mode
 * to decide whether to accept email, phone, or either as the identifier.
 */
class Login extends Component
{
    use ThrottlesAuthAttempts;

    public string $identifier = '';

    public string $password = '';

    public bool $remember = false;

    public function login(AuthenticationSettings $settings): void
    {
        $this->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureNotRateLimited('login', $this->identifier);

        $field = $this->resolveAuthField($settings);

        // Validate credentials WITHOUT logging in. The old flow called
        // Auth::attempt() (a full login → fires the Login event, runs the
        // new-device listener, and cycles the remember_token on every device)
        // and then Auth::logout() for the 2FA/moderation paths — so a
        // password-only attacker could spam new-device emails and invalidate
        // everyone's remember-me. We only call Auth::login() once every gate
        // (moderation + 2FA) has passed. (PNB-001)
        $provider = Auth::getProvider();
        $credentials = [$field => $this->identifier, 'password' => $this->password];
        $user = $provider->retrieveByCredentials($credentials);

        if ($user === null || ! $provider->validateCredentials($user, $credentials)) {
            $this->hitRateLimiter('login', $this->identifier);
            $this->addError('identifier', __('filament-panel-base::auth.credentials_mismatch'));

            return;
        }

        if ($user instanceof HasModerationStatus) {
            if ($user->isSuspended()) {
                $this->addError('identifier', __('filament-panel-base::auth.account_suspended'));

                return;
            }

            if ($user->isPending()) {
                $this->addError('identifier', __('filament-panel-base::auth.account_pending'));

                return;
            }
        }

        $this->clearRateLimiter('login', $this->identifier);

        if ($this->shouldChallengeForTwoFactor($user)) {
            // No login yet — just stash the pending user for the challenge.
            $challenge = app(TwoFactorChallengeSession::class);
            $challenge->stash($user, $this->remember);

            $this->redirect(route('two-factor.challenge'), navigate: true);

            return;
        }

        Auth::login($user, $this->remember);
        session()->regenerate();

        $this->redirect(session()->pull('url.intended', route('home')), navigate: true);
    }

    /**
     * Decide whether to interrupt this successful credential check with a
     * TOTP challenge. Skips when:
     *  - the 2FA module is disabled at the settings level
     *  - the User model doesn't use HasTwoFactorAuthentication
     *  - the user has not confirmed enrolment
     *  - a long-lived "remember this device" cookie is present + accepted
     */
    private function shouldChallengeForTwoFactor(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        try {
            $settings = app(TwoFactorSettings::class);
        } catch (\Throwable) {
            return false;
        }

        if (! $settings->enabled) {
            return false;
        }

        if (! in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)) {
            return false;
        }

        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        if ($settings->remember_device) {
            try {
                $challenge = app(TwoFactorChallengeSession::class);
                if ($challenge->deviceIsRemembered($user)) {
                    return false;
                }
            } catch (\Throwable) {
                // Fall through to challenge.
            }
        }

        return true;
    }

    public function render(AuthenticationSettings $settings): View
    {
        return view('filament-panel-base::livewire.auth.login', [
            'credentialsMode' => $settings->credentials_mode,
            'enabledSocialProviders' => $settings->social_providers_enabled,
        ])
            ->layout(config('filament-panel-base.auth.layout') ?: 'filament-panel-base::layouts.auth')
            ->title(__('filament-panel-base::auth.login_title'));
    }

    private function resolveAuthField(AuthenticationSettings $settings): string
    {
        return match ($settings->credentials_mode) {
            'phone' => 'phone',
            'both' => str_contains($this->identifier, '@') ? 'email' : 'phone',
            default => 'email',
        };
    }
}
