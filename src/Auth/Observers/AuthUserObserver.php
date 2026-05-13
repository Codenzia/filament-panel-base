<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Observers;

use Codenzia\FilamentPanelBase\Auth\Events\ModerationApproved;
use Codenzia\FilamentPanelBase\Auth\Events\ModerationPending;
use Codenzia\FilamentPanelBase\Auth\Events\ModerationSuspended;
use Codenzia\FilamentPanelBase\Auth\Notifications\AccountApprovedNotification;
use Codenzia\FilamentPanelBase\Auth\Notifications\AccountSuspendedNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * Bridges moderation events to host-side notifications.
 *
 * Registered conditionally by the AuthenticationPlugin only when the
 * host's User model implements HasModerationStatus. Hosts that want
 * custom notifications can either override these notification classes
 * via the container, or unregister this listener and subscribe directly.
 */
class AuthUserObserver
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ModerationApproved::class, [self::class, 'onApproved']);
        $events->listen(ModerationSuspended::class, [self::class, 'onSuspended']);
        $events->listen(ModerationPending::class, [self::class, 'onPending']);
    }

    public function onApproved(ModerationApproved $event): void
    {
        if ($previousStatus = $event->previousStatus) {
            // No notification when the previous status was already 'approved'
            // (defensive — ModeratesStatus already guards on this).
            if ($previousStatus === 'approved') {
                return;
            }
        }

        $this->notify($event->user, new AccountApprovedNotification);
    }

    public function onSuspended(ModerationSuspended $event): void
    {
        $this->notify($event->user, new AccountSuspendedNotification($event->reason));
    }

    public function onPending(ModerationPending $event): void
    {
        // No-op by default — hosts subscribe to ModerationPending to fan out
        // admin notifications. The user themselves should not be emailed
        // here; the registration page already messages them.
    }

    private function notify(Authenticatable $user, object $notification): void
    {
        if (! $user instanceof Model) {
            return;
        }

        if (! in_array(Notifiable::class, class_uses_recursive($user), true)) {
            return;
        }

        $user->notify($notification);
    }
}
