<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Contracts;

/**
 * Marker contract for users that can receive OTP codes through any of the
 * registered drivers (email, WhatsApp, SMS). The driver inspects the user
 * via this contract to resolve the destination identifier per channel.
 */
interface HasOtpVerification
{
    /**
     * Identifier to use for the given OTP channel, e.g. the phone for
     * 'whatsapp'/'twilio'/'vonage' and the email for 'email'.
     */
    public function getOtpTarget(string $channel): ?string;
}
