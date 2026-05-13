<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class VerifyEmailNotice extends Component
{
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
            ->layout(config('filament-panel-base.auth.layout', 'filament-panel-base::layouts.auth'))
            ->title(__('filament-panel-base::auth.verify_email_title'));
    }
}
