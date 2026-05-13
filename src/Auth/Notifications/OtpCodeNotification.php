<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email-channel OTP notification. Subject and body are translated via
 * the `filament-panel-base::auth.*` translation keys so hosts can override the
 * wording per locale.
 */
class OtpCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $code,
        public readonly array $context = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = $this->context['brand'] ?? config('app.name');
        $ttl = (int) ($this->context['ttl_minutes'] ?? config('filament-panel-base.auth.otp.ttl_minutes', 10));

        return (new MailMessage)
            ->subject(__('filament-panel-base::auth.otp_email_subject', ['brand' => $brand]))
            ->greeting(__('filament-panel-base::auth.otp_email_greeting'))
            ->line(__('filament-panel-base::auth.otp_email_intro', ['brand' => $brand]))
            ->line('**'.$this->code.'**')
            ->line(__('filament-panel-base::auth.otp_email_ttl', ['minutes' => $ttl]))
            ->line(__('filament-panel-base::auth.otp_email_ignore'));
    }
}
