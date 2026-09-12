<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Http\Requests\Concerns;

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;

/**
 * The set of OTP channels the public HTTP endpoints will act on.
 *
 * "Registered as a driver" and "offered to anonymous callers" are different
 * questions: the manager also resolves the null/logging driver, which answers
 * every request with a code that was never delivered. The allowlist is
 * therefore explicit — `otp_api.channels` when the host sets it, otherwise the
 * admin-managed list of drivers the settings UI itself permits.
 */
trait AllowsConfiguredOtpChannels
{
    /**
     * @return array<int, string>
     */
    protected function allowedOtpChannels(): array
    {
        $configured = config('filament-panel-base.otp_api.channels');

        if (is_array($configured)) {
            return array_values(array_filter(array_map(
                static fn ($channel): string => trim((string) $channel),
                $configured,
            )));
        }

        try {
            return app(AuthenticationSettings::class)->allowed_otp_drivers;
        } catch (\Throwable) {
            /** @var array<int, string> $fallback */
            $fallback = (array) config(
                'filament-panel-base.auth.otp.public_channels',
                ['email'],
            );

            return $fallback;
        }
    }
}
