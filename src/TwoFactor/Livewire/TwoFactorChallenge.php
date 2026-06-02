<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Livewire;

use Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts;
use Codenzia\FilamentPanelBase\TwoFactor\Concerns\HasTwoFactorAuthentication;
use Codenzia\FilamentPanelBase\TwoFactor\Events\TwoFactorChallengeFailed;
use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorChallengeSession;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Post-login challenge form. Mounted when AuthenticationLogin redirects
 * here after passing credentials but before completing Auth::login() —
 * the pending user lives in the session, not the auth guard.
 *
 * Verifies a TOTP code or recovery code via the User trait. On success,
 * logs the user in and redirects to the originally intended URL.
 */
class TwoFactorChallenge extends Component
{
    use ThrottlesAuthAttempts;

    #[Validate(['required', 'string', 'max:64'])]
    public string $code = '';

    public bool $rememberDevice = false;

    public function mount(TwoFactorChallengeSession $challenge): void
    {
        // Stale or direct visit — bounce back to login.
        if (! $challenge->hasPending()) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    public function submit(
        TwoFactorChallengeSession $challenge,
        TwoFactorSettings $settings,
    ): void {
        $this->validate();

        $user = $challenge->pendingUser();

        if ($user === null) {
            $challenge->forget();
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $this->ensureNotRateLimited('two-factor', (string) $user->getAuthIdentifier());

        if (! in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)) {
            // Defensive — the trait should always be present if we got here.
            $challenge->forget();
            $this->addError('code', __('filament-panel-base::two-factor.unavailable'));

            return;
        }

        if (! $user->verifyTwoFactorCode($this->code)) {
            $this->hitRateLimiter('two-factor', (string) $user->getAuthIdentifier());

            event(new TwoFactorChallengeFailed($user));
            $this->addError('code', __('filament-panel-base::two-factor.invalid_code'));

            return;
        }

        $remember = $challenge->pendingRemember();
        $intended = $challenge->pendingIntendedUrl();

        $this->clearRateLimiter('two-factor', (string) $user->getAuthIdentifier());
        $challenge->forget();

        Auth::login($user, $remember);
        session()->regenerate();

        if ($this->rememberDevice && $settings->remember_device) {
            $challenge->rememberDevice($user, $settings->remember_device_days);
        }

        $this->redirect($intended ?? route('home'), navigate: true);
    }

    public function render(): View
    {
        return view('filament-panel-base::livewire.auth.two-factor-challenge')
            ->layout(config('filament-panel-base.auth.layout') ?: 'filament-panel-base::layouts.auth')
            ->title(__('filament-panel-base::two-factor.challenge_title'));
    }
}
