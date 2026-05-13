<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Front-of-site Livewire login page. Reads AuthenticationSettings::credentials_mode
 * to decide whether to accept email, phone, or either as the identifier.
 */
class Login extends Component
{
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

        if (! Auth::attempt([$field => $this->identifier, 'password' => $this->password], $this->remember)) {
            $this->addError('identifier', __('panel-base::auth.credentials_mismatch'));

            return;
        }

        $user = Auth::user();

        if ($user instanceof HasModerationStatus) {
            if ($user->isSuspended()) {
                Auth::logout();
                $this->addError('identifier', __('panel-base::auth.account_suspended'));

                return;
            }

            if ($user->isPending()) {
                Auth::logout();
                $this->addError('identifier', __('panel-base::auth.account_pending'));

                return;
            }
        }

        session()->regenerate();

        $this->redirect(session()->pull('url.intended', route('home')), navigate: true);
    }

    public function render(AuthenticationSettings $settings): View
    {
        return view('panel-base::livewire.auth.login', [
            'credentialsMode' => $settings->credentials_mode,
            'enabledSocialProviders' => $settings->social_providers_enabled,
        ])
            ->layout(config('filament-panel-base.auth.layout', 'panel-base::layouts.auth'))
            ->title(__('panel-base::auth.login_title'));
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
