<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use Codenzia\FilamentPanelBase\Auth\Concerns\ResolvesAuthLayout;
use Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Codenzia\FilamentPanelBase\TwoFactor\Exceptions\TwoFactorUnavailableException;
use Codenzia\FilamentPanelBase\TwoFactor\Services\LoginChallengeDecider;
use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorChallengeSession;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Front-of-site Livewire login page. Reads AuthenticationSettings::credentials_mode
 * to decide whether to accept email, phone, or either as the identifier.
 */
class Login extends Component
{
    use ResolvesAuthLayout;
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

        $field = $this->resolveAuthField($settings);

        // Normalise the identifier the same way registration stored it, so a
        // mixed-case email (PNB-013) or a locally-typed phone number (PNB-014)
        // still resolves to the persisted account.
        $this->identifier = $this->normaliseIdentifier($field, $settings);

        $this->ensureNotRateLimited('login', $this->identifier);

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

        try {
            $challengeRequired = app(LoginChallengeDecider::class)->shouldChallenge($user);
        } catch (TwoFactorUnavailableException) {
            $this->addError('identifier', __('filament-panel-base::two-factor.service_unavailable'));

            return;
        }

        if ($challengeRequired) {
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

    public function render(AuthenticationSettings $settings): View
    {
        return $this->withAuthLayout(view('filament-panel-base::livewire.auth.login', [
            'credentialsMode' => $settings->credentials_mode,
            'enabledSocialProviders' => $settings->social_providers_enabled,
        ]))
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

    /**
     * Normalise the submitted identifier into the form registration persisted:
     * lower-cased email, or an E.164 phone number.
     */
    private function normaliseIdentifier(string $field, AuthenticationSettings $settings): string
    {
        return $field === 'email'
            ? mb_strtolower(trim($this->identifier))
            : $this->normalisePhoneIdentifier($settings);
    }

    /**
     * Turn a locally-typed phone number into the same E.164 string that
     * registration stored. Registration prepends the default country code to a
     * national number, so a login submission of `0791234567` (or `791234567`)
     * must resolve to `<dial code>791234567` — the leading national-trunk zero
     * is dropped. A value already in `+` international form is used verbatim.
     */
    private function normalisePhoneIdentifier(AuthenticationSettings $settings): string
    {
        $candidate = trim($this->identifier);

        if ($candidate === '' || str_starts_with($candidate, '+')) {
            return $candidate;
        }

        $national = ltrim(preg_replace('/\D/', '', $candidate) ?? '', '0');

        return $settings->default_country_code.$national;
    }
}
