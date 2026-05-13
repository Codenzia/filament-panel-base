<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $email = '';

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email:rfc'],
        ]);

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
