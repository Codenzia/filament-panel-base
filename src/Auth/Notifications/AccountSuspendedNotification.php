<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $reason = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = config('app.name');

        return (new MailMessage)
            ->subject(__('panel-base::auth.moderation_suspended_subject', ['brand' => $brand]))
            ->line(__('panel-base::auth.moderation_suspended_body', ['reason' => $this->reason ?? '—']));
    }
}
