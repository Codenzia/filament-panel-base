<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Drivers\Otp;

use Illuminate\Support\Facades\Log;

/**
 * No-op driver — writes the code to the application log so developers can
 * recover it without configuring a real transport. Useful in local/CI.
 */
class NullOtpDriver implements OtpDriver
{
    public function send(string $target, string $code, array $context = []): void
    {
        Log::info('[fpb-auth] Null OTP driver — code suppressed in production-style transport', [
            'target' => $target,
            'code' => $code,
            'context' => $context,
        ]);
    }

    public function channel(): string
    {
        return 'null';
    }
}
