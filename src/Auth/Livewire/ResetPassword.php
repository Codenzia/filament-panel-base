<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class ResetPassword extends Component
{
    use ThrottlesAuthAttempts;

    #[Locked]
    public string $token = '';

    #[Url]
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->ensureNotRateLimited('reset', $this->email, 'email');

        $status = Password::reset(
            [
                'token' => $this->token,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ],
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->hitRateLimiter('reset', $this->email);
            $this->addError('email', __($status));

            return;
        }

        $this->clearRateLimiter('reset', $this->email);

        session()->flash('status', __('filament-panel-base::auth.reset_done'));

        $this->redirect(route('login'), navigate: true);
    }

    public function render(): View
    {
        return view('filament-panel-base::livewire.auth.reset-password')
            ->layout(config('filament-panel-base.auth.layout') ?: 'filament-panel-base::layouts.auth')
            ->title(__('filament-panel-base::auth.reset_title'));
    }
}
