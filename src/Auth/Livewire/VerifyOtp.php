<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts;
use Codenzia\FilamentPanelBase\Auth\Contracts\HasOtpVerification;
use Codenzia\FilamentPanelBase\Auth\Contracts\HasPhone;
use Codenzia\FilamentPanelBase\Auth\Services\OtpService;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Generic OTP verification page. Works for any channel — the actual transport
 * is resolved by the OtpService from AuthenticationSettings::otp_driver. The
 * page knows nothing about the underlying driver.
 */
class VerifyOtp extends Component
{
    use ThrottlesAuthAttempts;

    public string $code = '';

    public function verify(OtpService $otp, AuthenticationSettings $settings): void
    {
        $length = $settings->otp_code_length;

        $this->validate([
            'code' => ['required', 'string', 'size:'.$length],
        ]);

        $target = $this->resolveTarget($settings);

        if ($target === null) {
            $this->addError('code', __('filament-panel-base::auth.verify_otp_invalid'));

            return;
        }

        $this->ensureNotRateLimited('otp-verify', $target, 'code');

        if (! $otp->verify($target, $this->code, $settings->otp_driver, Auth::id())) {
            $this->hitRateLimiter('otp-verify', $target);
            $this->addError('code', __('filament-panel-base::auth.verify_otp_invalid'));

            return;
        }

        $this->clearRateLimiter('otp-verify', $target);

        $user = Auth::user();

        if ($user instanceof HasPhone) {
            $user->markPhoneVerified();
        }

        $this->redirect(route('home'), navigate: true);
    }

    public function resend(OtpService $otp, AuthenticationSettings $settings): void
    {
        $target = $this->resolveTarget($settings);

        if ($target === null) {
            return;
        }

        // Resending rotates the code (resetting its per-code attempt counter),
        // so a resend must consume the same verify budget — otherwise the
        // effective guess budget becomes attempts × resend rate.
        $this->ensureNotRateLimited('otp-verify', $target, 'code');
        $this->hitRateLimiter('otp-verify', $target);

        try {
            $otp->send($target, $settings->otp_driver, context: [
                'brand' => config('app.name'),
                'ttl_minutes' => $settings->otp_ttl_minutes,
                'locale' => app()->getLocale(),
            ], userId: Auth::id());

            session()->flash('status', __('filament-panel-base::auth.verify_otp_resent'));
        } catch (\RuntimeException $exception) {
            $this->addError('code', $exception->getMessage());
        }
    }

    public function render(AuthenticationSettings $settings): View
    {
        $target = $this->resolveTarget($settings) ?? '';

        return view('filament-panel-base::livewire.auth.verify-otp', [
            'channel' => $settings->otp_driver,
            'channelLabel' => __('filament-panel-base::auth.channel.'.$settings->otp_driver),
            'length' => $settings->otp_code_length,
            'target' => $target,
        ])
            ->layout(config('filament-panel-base.auth.layout') ?: 'filament-panel-base::layouts.auth')
            ->title(__('filament-panel-base::auth.verify_otp_title', [
                'channel' => __('filament-panel-base::auth.channel.'.$settings->otp_driver),
            ]));
    }

    private function resolveTarget(AuthenticationSettings $settings): ?string
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        if ($user instanceof HasOtpVerification) {
            return $user->getOtpTarget($settings->otp_driver);
        }

        // Reasonable defaults — email driver -> user's email; everything else -> phone.
        if ($settings->otp_driver === 'email') {
            return $user->email ?? null;
        }

        if ($user instanceof HasPhone) {
            return $user->getPhone();
        }

        return null;
    }
}
