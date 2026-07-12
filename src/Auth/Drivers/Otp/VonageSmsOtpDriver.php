<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Drivers\Otp;

use Codenzia\FilamentPanelBase\Auth\Exceptions\OtpDeliveryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS OTP via Vonage (formerly Nexmo). Hits the Vonage SMS API directly so
 * the driver doesn't require the vonage/client SDK or the
 * laravel/vonage-notification-channel package.
 *
 * Credentials are read from config (env-driven). When credentials are
 * missing, the driver logs the code at warning level so local development
 * keeps working.
 */
class VonageSmsOtpDriver implements OtpDriver
{
    use MasksOtpCode;

    public function __construct(
        private readonly string $key,
        private readonly string $secret,
        private readonly string $from,
    ) {}

    public function send(string $target, string $code, array $context = []): void
    {
        if ($this->key === '' || $this->secret === '') {
            Log::warning('[fpb-auth] Vonage credentials missing — SMS OTP suppressed.', [
                'target' => $target,
                'code' => self::maskCode($code),
            ]);

            if (app()->environment('local', 'testing')) {
                return;
            }

            throw new OtpDeliveryException('Vonage credentials are not configured.');
        }

        $body = $this->renderBody($code, $context);

        try {
            Http::asForm()
                ->post('https://rest.nexmo.com/sms/json', [
                    'api_key' => $this->key,
                    'api_secret' => $this->secret,
                    'from' => $this->from,
                    'to' => ltrim($target, '+'),
                    'text' => $body,
                ])
                ->throw();
        } catch (\Throwable $exception) {
            Log::error('[fpb-auth] Vonage SMS OTP delivery failed: '.$exception->getMessage(), [
                'target' => $target,
                'exception' => $exception::class,
            ]);

            throw new OtpDeliveryException('Vonage SMS OTP delivery failed.', 0, $exception);
        }
    }

    public function channel(): string
    {
        return 'vonage';
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
