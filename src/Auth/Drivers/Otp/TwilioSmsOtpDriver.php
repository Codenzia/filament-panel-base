<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Drivers\Otp;

use Codenzia\FilamentPanelBase\Auth\Exceptions\OtpDeliveryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS OTP via Twilio. Hits the Twilio Messages API directly with HTTP
 * Basic auth so the driver doesn't require the heavy twilio/sdk package.
 *
 * Credentials are read from config (env-driven). When credentials are
 * missing, the driver logs the code at warning level so local development
 * keeps working.
 */
class TwilioSmsOtpDriver implements OtpDriver
{
    use MasksOtpCode;

    public function __construct(
        private readonly string $sid,
        private readonly string $token,
        private readonly string $from,
    ) {}

    public function send(string $target, string $code, array $context = []): void
    {
        if ($this->sid === '' || $this->token === '' || $this->from === '') {
            Log::warning('[fpb-auth] Twilio credentials missing — SMS OTP suppressed.', [
                'target' => $target,
                'code' => self::maskCode($code),
            ]);

            if (app()->environment('local', 'testing')) {
                return;
            }

            throw new OtpDeliveryException('Twilio credentials are not configured.');
        }

        $body = $this->renderBody($code, $context);

        try {
            Http::withBasicAuth($this->sid, $this->token)
                ->asForm()
                ->post(
                    sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $this->sid),
                    [
                        'From' => $this->from,
                        'To' => $target,
                        'Body' => $body,
                    ]
                )
                ->throw();
        } catch (\Throwable $exception) {
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
