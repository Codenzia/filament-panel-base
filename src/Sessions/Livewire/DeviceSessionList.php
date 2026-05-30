<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sessions\Livewire;

use Codenzia\FilamentPanelBase\Sessions\Services\DeviceSessionRepository;
use Codenzia\FilamentPanelBase\Sessions\Settings\SessionManagementSettings;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
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

        Notification::make()
            ->title(__('filament-panel-base::sessions.logout_others_notification', ['count' => $count]))
            ->success()
            ->send();
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
