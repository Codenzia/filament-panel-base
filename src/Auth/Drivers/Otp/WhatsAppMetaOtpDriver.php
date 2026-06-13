<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Drivers\Otp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp OTP via Meta Cloud API.
 *
 * Ported from aqarkom's WhatsAppVerificationService. Credentials are read
 * from config (env-driven) — never the database. When credentials are
 * missing, the driver logs the code at warning level so local development
 * still works without configuring Meta.
 */
class WhatsAppMetaOtpDriver implements OtpDriver
{
    use MasksOtpCode;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $phoneId,
        private readonly string $accessToken,
        private readonly string $templateName,
        private readonly string $templateLanguage,
    ) {}

    public function send(string $target, string $code, array $context = []): void
    {
        if ($this->phoneId === '' || $this->accessToken === '') {
            Log::warning('[fpb-auth] WhatsApp Meta credentials missing — OTP suppressed.', [
                'target' => $target,
                'code' => self::maskCode($code),
            ]);

            return;
        }

        try {
            Http::withToken($this->accessToken)
                ->post(sprintf('%s/%s/messages', rtrim($this->apiUrl, '/'), $this->phoneId), [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($target, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => $this->templateName,
                        'language' => ['code' => $this->templateLanguage],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $code],
                                ],
                            ],
                        ],
                    ],
                ])
                ->throw();
        } catch (\Throwable $exception) {
            Log::error('[fpb-auth] WhatsApp OTP delivery failed: '.$exception->getMessage(), [
                'target' => $target,
                'exception' => $exception::class,
            ]);
        }
    }

    public function channel(): string
    {
        return 'whatsapp';
    }
}
