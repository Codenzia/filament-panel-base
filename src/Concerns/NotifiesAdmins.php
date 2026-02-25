<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Shared admin notification logic for lead capture forms.
 *
 * Sends notifications to admin-role users and optionally the content author.
 */
trait NotifiesAdmins
{
    /**
     * Notify all admin users and optionally the content author.
     */
    protected function notifyAdminsAndAuthor(BaseNotification $notification, ?Model $author = null): void
    {
        $userModel = config('filament-panel-base.user_model', \App\Models\User::class);
        $adminRole = config('filament-panel-base.admin_role', 'super_admin');

        $recipients = $userModel::whereHas('roles', fn ($q) => $q->where('name', $adminRole))->get();

        if ($author instanceof $userModel) {
            $recipients = $recipients->push($author)->unique('id');
        }

        Notification::send($recipients, $notification);
    }
}
