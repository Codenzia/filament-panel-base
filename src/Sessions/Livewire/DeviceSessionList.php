<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sessions\Livewire;

use Codenzia\FilamentPanelBase\Sessions\Services\DeviceSessionRepository;
use Codenzia\FilamentPanelBase\Sessions\Settings\SessionManagementSettings;
use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorChallengeSession;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Lists every active session for the current user (queried straight from
 * Laravel's `sessions` table). Renders inside the profile slide-over via
 * the HasSessionManagementProfileTab trait, which mounts this component
 * with @livewire(...).
 *
 * Gracefully degrades when the session driver isn't database — shows a
 * friendly "configure database sessions to see this" notice rather than
 * crashing.
 */
class DeviceSessionList extends Component
{
    public bool $confirmingRevoke = false;

    public ?string $sessionToRevoke = null;

    public function revoke(DeviceSessionRepository $repo, string $sessionId): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $currentId = session()->getId();

        if ($sessionId === $currentId) {
            // Revoking the current session = log yourself out. Use the
            // standard Auth logout instead so the chrome refreshes.
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            $this->redirect(route('login'), navigate: false);

            return;
        }

        if ($repo->revoke($user, $sessionId)) {
            // Deleting the row logs that device out on its next request, but a
            // remember-me or 2FA "remember device" cookie would silently
            // resurrect it. Cycle those credentials so the revocation sticks.
            $this->invalidateOtherDeviceCredentials($user);

            Notification::make()
                ->title(__('filament-panel-base::sessions.revoked_notification'))
                ->success()
                ->send();
        }
    }

    public function logoutOtherDevices(
        DeviceSessionRepository $repo,
        SessionManagementSettings $settings,
    ): void {
        if (! $settings->allow_logout_other_devices) {
            return;
        }

        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $count = $repo->revokeAllExcept($user, session()->getId());

        // Killing the session rows isn't enough on its own — remember-me and
        // 2FA "remember device" cookies on the other browsers would let them
        // log back in. Rotate the underlying credentials and re-establish the
        // current browser so it alone survives.
        $this->invalidateOtherDeviceCredentials($user);

        Notification::make()
            ->title(__('filament-panel-base::sessions.logout_others_notification', ['count' => $count]))
            ->success()
            ->send();
    }

    /**
     * Invalidate the credentials that survive a raw session-row delete, then
     * re-establish them for the *current* browser only.
     *
     * Laravel stores a single shared `remember_token` per user, so the only
     * way to invalidate remember-me on the other devices is to cycle it —
     * which also drops this browser's recaller cookie. The 2FA remember-device
     * cookie is invalidated the same way via its rotatable nonce. Both are
     * re-issued for the current browser afterwards when they were in use, so
     * the person performing the action stays signed in and trusted here.
     */
    private function invalidateOtherDeviceCredentials(Authenticatable $user): void
    {
        $guard = Auth::guard();
        $recallerName = method_exists($guard, 'getRecallerName') ? $guard->getRecallerName() : null;
        $hadRemember = $recallerName !== null && request()->cookies->has($recallerName);

        $challenge = app(TwoFactorChallengeSession::class);
        $wasTwoFactorTrusted = method_exists($user, 'rotateTwoFactorRememberToken')
            && $challenge->deviceIsRemembered($user);

        if (method_exists($user, 'rotateTwoFactorRememberToken')) {
            $user->rotateTwoFactorRememberToken();
        }

        if (method_exists($user, 'setRememberToken')) {
            $user->setRememberToken(Str::random(60));
            $user->save();
        }

        // Re-establish the current browser with the rotated tokens.
        if ($hadRemember) {
            Auth::login($user, true);
        }

        if ($wasTwoFactorTrusted) {
            $challenge->rememberDevice($user, app(TwoFactorSettings::class)->remember_device_days);
        }
    }

    public function render(SessionManagementSettings $settings, DeviceSessionRepository $repo): View
    {
        $user = Auth::user();

        $driverOk = $repo->driverIsDatabase();
        $sessions = collect();

        if ($driverOk && $user !== null) {
            try {
                $sessions = $repo->forUser($user, session()->getId());
            } catch (\Throwable) {
                $driverOk = false;
            }
        }

        return view('filament-panel-base::livewire.sessions.device-session-list', [
            'driverOk' => $driverOk,
            'sessions' => $sessions,
            'idleThresholdMinutes' => $settings->idle_threshold_minutes,
            'allowLogoutOthers' => $settings->allow_logout_other_devices,
        ]);
    }
}
