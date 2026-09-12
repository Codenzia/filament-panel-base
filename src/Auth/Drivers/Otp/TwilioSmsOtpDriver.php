<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Drivers\Otp;

use Codenzia\FilamentPanelBase\Auth\Exceptions\OtpDeliveryException;
use Codenzia\LaravelSms\Exceptions\SmsException;
use Codenzia\LaravelSms\Facades\Sms;
use Illuminate\Support\Facades\Log;

/**
 * SMS OTP over Twilio, delivered through codenzia/laravel-sms.
 *
 * The raw Twilio transport (credentials, HTTP call, error handling) now lives
 * in laravel-sms' TwilioSmsDriver; this driver keeps only the "conversation"
 * concerns — rendering the localised message body and mapping transport
 * failures onto the auth module's OtpDeliveryException. Twilio credentials are
 * configured in laravel-sms (`sms.drivers.twilio`), not here.
 */
class TwilioSmsOtpDriver implements OtpDriver
{
    public function send(string $target, string $code, array $context = []): void
    {
        try {
            Sms::to($target)->via('twilio')->send($this->renderBody($code, $context));
        } catch (SmsException $exception) {
            Log::error('[fpb-auth] Twilio SMS OTP delivery failed: '.$exception->getMessage(), [
                'target' => $target,
                'exception' => $exception::class,
            ]);

            throw new OtpDeliveryException('Twilio SMS OTP delivery failed.', 0, $exception);
        }
    }

    public function channel(): string
    {
        return 'twilio';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderBody(string $code, array $context): string
    {
        $brand = $context['brand'] ?? config('app.name');

        return trans(
            'filament-panel-base::auth.otp_sms_body',
            ['code' => $code, 'brand' => $brand],
            null,
            (string) ($context['locale'] ?? app()->getLocale())
        );
    }
}
