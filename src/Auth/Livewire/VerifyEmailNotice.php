<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class VerifyEmailNotice extends Component
{
    use ThrottlesAuthAttempts;

    public function resend(): void
    {
        $user = Auth::user();

        if (! $user instanceof MustVerifyEmail) {
            return;
        }

        if ($user->hasVerifiedEmail()) {
            $this->redirect(route('home'), navigate: true);

            return;
        }

        // Each resend triggers a real mail send; cap it on the user id so a
        // logged-in attacker can't blow through the mail budget.
        $userKey = (string) $user->getAuthIdentifier();
        $this->ensureNotRateLimited('email-resend', $userKey, 'email');
        $this->hitRateLimiter('email-resend', $userKey);

        $user->sendEmailVerificationNotification();

        session()->flash('status', __('filament-panel-base::auth.verify_email_resent'));
    }

    public function render(): View
    {
        $email = Auth::user()?->email ?? '';

        return view('filament-panel-base::livewire.auth.verify-email-notice', [
            'email' => $email,
            'verified' => Auth::user()?->hasVerifiedEmail() ?? false,
        ])
            ->layout(config('filament-panel-base.auth.layout') ?: 'filament-panel-base::layouts.auth')
            ->title(__('filament-panel-base::auth.verify_email_title'));
    }
}
