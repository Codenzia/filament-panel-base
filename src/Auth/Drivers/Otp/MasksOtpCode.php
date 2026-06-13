<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Drivers\Otp;

/**
 * Shared helper for OTP drivers: never write a full OTP code to the log.
 * Keeps the first and last digit for support correlation while masking the
 * rest, so a leaked log line is not directly usable to verify.
 */
trait MasksOtpCode
{
    protected static function maskCode(string $code): string
    {
        $length = strlen($code);

        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        return $code[0].str_repeat('*', $length - 2).$code[$length - 1];
    }
}
