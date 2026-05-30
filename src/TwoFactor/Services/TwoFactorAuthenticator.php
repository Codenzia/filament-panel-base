<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Services;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

/**
 * Thin wrapper around pragmarx/google2fa. Centralises secret generation,
 * code verification, and provisioning-URI/QR-code rendering so callers
 * don't reach into Google2FA directly.
 *
 * Google2FA + bacon/bacon-qr-code are listed as composer `suggest:` so the
 * dependency stays optional for hosts that don't use 2FA. Calling any
 * method on this class without those packages installed throws a clear
 * RuntimeException.
 */
class TwoFactorAuthenticator
{
    public function __construct(private TwoFactorSettings $settings) {}

    /**
     * Generate a fresh base32 TOTP secret. 26 chars = 130 bits of entropy,
     * matching Fortify's default. Returns a plain string; callers must
     * persist it on the user model where the trait will encrypt at rest.
     */
    public function generateSecret(): string
    {
        return $this->google2fa()->generateSecretKey(32);
    }

    /**
     * Verify a 6-digit code against a stored secret. Returns true on match,
     * within the configured acceptance window.
     */
    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($code === '') {
            return false;
        }

        try {
            return (bool) $this->google2fa()->verifyKey(
                $secret,
                $code,
                $this->settings->window,
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Build the `otpauth://` provisioning URI that authenticator apps scan.
     * The label is `{issuer}:{account}` and the issuer is also passed as a
     * query param so apps that ignore the label still display branding.
     */
    public function provisioningUri(string $secret, string $accountName, ?string $issuer = null): string
    {
        $resolvedIssuer = $issuer
            ?? $this->settings->issuer
            ?? (string) config('app.name', 'App');

        return $this->google2fa()->getQRCodeUrl(
            $resolvedIssuer,
            $accountName,
            $secret,
        );
    }

    /**
     * Render the provisioning URI as an inline SVG QR code. Returns the
     * SVG markup so the caller can drop it into a Blade view without
     * temporary files or extra HTTP requests.
     */
    public function qrCodeSvg(string $provisioningUri, int $size = 220): string
    {
        if (! class_exists(Writer::class)) {
            throw new RuntimeException(
                'bacon/bacon-qr-code is required to render the 2FA enrolment QR code. '
                .'Install it with: composer require bacon/bacon-qr-code'
            );
        }

        $renderer = new ImageRenderer(
            new RendererStyle($size, 0),
            new SvgImageBackEnd(),
        );

        return (new Writer($renderer))->writeString($provisioningUri);
    }

    private function google2fa(): Google2FA
    {
        if (! class_exists(Google2FA::class)) {
            throw new RuntimeException(
                'pragmarx/google2fa is required for the two-factor authentication module. '
                .'Install it with: composer require pragmarx/google2fa'
            );
        }

        $g2fa = new Google2FA();
        $g2fa->setOneTimePasswordLength($this->settings->digits);
        $g2fa->setKeyRegeneration($this->settings->period);

        return $g2fa;
    }
}
