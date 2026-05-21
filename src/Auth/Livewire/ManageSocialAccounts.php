<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use Codenzia\FilamentPanelBase\Auth\Contracts\SupportsSocialLogin;
use Codenzia\FilamentPanelBase\Auth\Models\SocialAccount;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Front-of-site Livewire component for the "connected accounts" profile UI.
 * Lists the current user's social_accounts, exposes "Connect {Provider}"
 * buttons for the remaining enabled providers, and supports disconnecting
 * — except when removing the link would leave the user with no way to
 * sign in (no password and no other social link).
 *
 * Not auto-mounted as a route or Filament page. Hosts opt in by placing
 * `@livewire('filament-panel-base::auth.manage-social-accounts')` on their
 * profile page or by registering it as a Filament panel page.
 */
class ManageSocialAccounts extends Component
{
    public function disconnect(int $accountId): void
    {
        $user = Auth::user();

        if (! $user instanceof SupportsSocialLogin) {
            return;
        }

        /** @var SocialAccount|null $account */
        $account = $user->socialAccounts()->find($accountId);

        if ($account === null) {
            return;
        }

        if (! $this->userHasOtherSignInMethod($user, $account)) {
            session()->flash(
                'error',
                __('filament-panel-base::auth.social_disconnect_blocked')
            );

            return;
        }

        $account->delete();

        session()->flash(
            'status',
            __('filament-panel-base::auth.social_disconnect_success', [
                'provider' => ucfirst($account->provider),
            ])
        );
    }

    public function render(AuthenticationSettings $settings): View
    {
        $user = Auth::user();

        $connected = $user instanceof SupportsSocialLogin
            ? $user->socialAccounts()->orderBy('provider')->get()
            : collect();

        $connectedKeys = $connected->pluck('provider')->all();

        $availableProviders = array_values(array_diff(
            $settings->social_providers_enabled,
            $connectedKeys
        ));

        return view('filament-panel-base::livewire.auth.manage-social-accounts', [
            'connectedAccounts' => $connected,
            'availableProviders' => $availableProviders,
            'canDisconnect' => function (SocialAccount $account) use ($user): bool {
                return $user instanceof SupportsSocialLogin
                    && $this->userHasOtherSignInMethod($user, $account);
            },
        ]);
    }

    /**
     * True when removing the given social account still leaves the user
     * a way to authenticate (password or another linked social account).
     */
    private function userHasOtherSignInMethod(SupportsSocialLogin $user, SocialAccount $account): bool
    {
        /** @var Model $user */
        $password = $user->getAttribute('password');

        if (is_string($password) && $password !== '' && Hash::info($password)['algoName'] !== 'unknown') {
            return true;
        }

        return $user->socialAccounts()
            ->where('id', '!=', $account->getKey())
            ->exists();
    }
}
