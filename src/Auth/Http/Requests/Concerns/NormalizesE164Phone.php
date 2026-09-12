<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Http\Requests\Concerns;

use Codenzia\LaravelSms\Exceptions\SmsException;
use Codenzia\LaravelSms\Facades\Sms;

/**
 * Normalise phone input to E.164 using laravel-sms' libphonenumber-backed
 * validator. Returns null when the value cannot be parsed as a valid number,
 * so callers can fail closed (leave the raw value for a format rule to reject).
 */
trait NormalizesE164Phone
{
    protected function normalizeE164(string $value): ?string
    {
        $candidate = trim($value);

        if ($candidate === '') {
            return null;
        }

        try {
            return Sms::validatePhoneNumber($candidate)->e164;
        } catch (SmsException) {
            return null;
        }
    }
}
