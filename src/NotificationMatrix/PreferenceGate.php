<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\NotificationMatrix;

use Illuminate\Database\Eloquent\Model;

/**
 * Convenience wrapper for the common call site: a Laravel Notification's
 * `via()` method, which returns the channel list to attempt.
 *
 *     public function via(object $notifiable): array
 *     {
 *         return PreferenceGate::filterChannels(
 *             $notifiable,
 *             'task-off.task.assigned',
 *             ['database', 'mail'],
 *         );
 *     }
 *
 * Simply runs each candidate channel through
 * {@see NotificationPreferences::allows()} and keeps the ones that pass.
 */
class PreferenceGate
{
    /**
     * @param  array<int, string>  $channels
     * @return array<int, string>
     */
    public static function filterChannels(Model $user, string $triggerKey, array $channels): array
    {
        return array_values(array_filter(
            $channels,
            fn (string $channel): bool => NotificationPreferences::allows($user, $triggerKey, $channel),
        ));
    }
}
