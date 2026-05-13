<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Drivers\Otp;

/**
 * Transport contract for OTP delivery. Implementations are stateless — the
 * OtpService owns code generation, persistence, and verification; drivers
 * are responsible only for getting the code to the user.
 */
interface OtpDriver
{
    /**
     * Deliver the OTP code to the destination address (phone number or email).
     *
     * @param  array<string, mixed>  $context  Locale, user model, message variant, etc.
     */
    public function send(string $target, string $code, array $context = []): void;

    /**
     * Canonical channel identifier matching the AuthenticationSettings::otp_driver value.
     */
    public function channel(): string;
}
