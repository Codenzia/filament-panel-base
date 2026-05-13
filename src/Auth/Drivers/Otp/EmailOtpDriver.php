<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Drivers\Otp;

use Codenzia\FilamentPanelBase\Auth\Notifications\OtpCodeNotification;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Email OTP — sends the code via Laravel's notification system using the
 * `mail` channel. Uses an anonymous notifiable so the driver is target-
 * agnostic; hosts that want richer mailable templating can override the
 * OtpCodeNotification with their own implementation in the container.
 */
class EmailOtpDriver implements OtpDriver
{
    public function send(string $target, string $code, array $context = []): void
    {
        (new AnonymousNotifiable)
            ->route('mail', $target)
            ->notify(new OtpCodeNotification($code, $context));
    }

    public function channel(): string
    {
        return 'email';
    }
}
