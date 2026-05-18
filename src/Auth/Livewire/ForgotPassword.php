<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ForgotPassword extends Component
{
    use ThrottlesAuthAttempts;

    public string $email = '';

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email:rfc'],
        ]);

        // Every attempt counts regardless of whether the address exists — the
        // status flash deliberately doesn't reveal that, so a leaked count
        // through unthrottled retries would defeat the privacy promise.
        $this->ensureNotRateLimited('forgot', $this->email, 'email');
        $this->hitRateLimiter('forgot', $this->email);

        // Always show the same "if it exists, we sent it" message to avoid
        // leaking which addresses are registered.
        Password::sendResetLink(['email' => $this->email]);

        session()->flash('status', __('filament-panel-base::auth.forgot_sent'));
    }

    public function render(): View
    {
        return view('filament-panel-base::livewire.auth.forgot-password')
            ->layout(config('filament-panel-base.auth.layout', 'filament-panel-base::layouts.auth'))
            ->title(__('filament-panel-base::auth.forgot_title'));
    }
}
