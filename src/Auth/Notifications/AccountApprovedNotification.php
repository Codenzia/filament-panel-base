<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject(__('filament-panel-base::auth.moderation_approved_subject', ['brand' => $brand]))
            ->line(__('filament-panel-base::auth.moderation_approved_body', ['brand' => $brand]))
            ->action(__('filament-panel-base::auth.sign_in'), url('/'));
    }
}
