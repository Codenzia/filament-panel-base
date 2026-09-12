<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Contracts;

use Illuminate\Http\Request;

/**
 * Host-app hook for minting an authentication token after a successful OTP
 * verify on the headless REST API. panel-base deliberately does NOT issue
 * Sanctum/Passport tokens itself — the host owns identity and provisioning.
 *
 * Point `filament-panel-base.otp_api.token_issuer` at an implementation (or an
 * invokable with the same signature). The returned array is merged into the
 * verify response JSON, e.g. `['token' => $plainTextToken]`.
 */
interface OtpTokenIssuer
{
    /**
     * @return array<string, mixed>
     */
    public function issue(string $target, Request $request): array;
}
