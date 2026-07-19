<?php

use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorAuthenticator;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->digits = 6;
    $settings->period = 30;
    $settings->window = 1;
    $settings->issuer = 'TestIssuer';

    $this->settings = $settings;
    $this->auth = new TwoFactorAuthenticator($settings);
});

it('generates a base32 secret of the expected length', function (): void {
    $secret = $this->auth->generateSecret();

    expect($secret)->toBeString();
    expect(strlen($secret))->toBe(32);
    // Base32 alphabet is A-Z and 2-7
    expect(preg_match('/^[A-Z2-7]+$/', $secret))->toBe(1);
});

it('verifies a TOTP code generated for its own secret', function (): void {
    $secret = $this->auth->generateSecret();
    $google2fa = new Google2FA;
    $google2fa->setOneTimePasswordLength(6);
    $code = $google2fa->getCurrentOtp($secret);

    expect($this->auth->verify($secret, $code))->toBeTrue();
});

it('rejects an invalid TOTP code', function (): void {
    $secret = $this->auth->generateSecret();

    expect($this->auth->verify($secret, '000000'))->toBeFalse();
});

it('rejects an empty code', function (): void {
    $secret = $this->auth->generateSecret();

    expect($this->auth->verify($secret, ''))->toBeFalse();
    expect($this->auth->verify($secret, '   '))->toBeFalse();
});

it('accepts a TOTP once but rejects the same code on replay when the guard is on (PNB-007)', function (): void {
    $secret = $this->auth->generateSecret();
    $google2fa = new Google2FA;
    $google2fa->setOneTimePasswordLength(6);
    $code = $google2fa->getCurrentOtp($secret);

    // First submission wins.
    expect($this->auth->verify($secret, $code, guardReplay: true))->toBeTrue();

    // Replaying the exact same still-valid code is rejected by the single-use
    // cache guard, even though the TOTP itself is still within the window.
    expect($this->auth->verify($secret, $code, guardReplay: true))->toBeFalse();
});

it('does not arm the replay guard during enrolment confirmation (PNB-007)', function (): void {
    $secret = $this->auth->generateSecret();
    $google2fa = new Google2FA;
    $google2fa->setOneTimePasswordLength(6);
    $code = $google2fa->getCurrentOtp($secret);

    // guardReplay defaults to false — confirming twice is fine (one-shot flow).
    expect($this->auth->verify($secret, $code))->toBeTrue();
    expect($this->auth->verify($secret, $code))->toBeTrue();
});

it('returns false without throwing on a malformed secret (PNB-019)', function (): void {
    // A secret with non-base32 characters makes Google2FA throw an
    // InvalidCharactersException; the typed catch turns that into a plain
    // "invalid code" false rather than bubbling the exception out of verify().
    expect($this->auth->verify('!!!not-base32!!!', '123456', guardReplay: true))->toBeFalse();
});

it('builds a provisioning URI including the configured issuer', function (): void {
    $secret = $this->auth->generateSecret();
    $uri = $this->auth->provisioningUri($secret, 'alice@example.com');

    expect($uri)->toStartWith('otpauth://totp/');
    expect($uri)->toContain('TestIssuer');
    expect($uri)->toContain('alice%40example.com');
    expect($uri)->toContain('secret='.$secret);
});

it('honours an issuer override on provisioningUri', function (): void {
    $secret = $this->auth->generateSecret();
    $uri = $this->auth->provisioningUri($secret, 'alice@example.com', 'Override Co.');

    expect($uri)->toContain('Override');
});

it('renders an SVG QR code for a provisioning URI', function (): void {
    $secret = $this->auth->generateSecret();
    $uri = $this->auth->provisioningUri($secret, 'alice@example.com');

    $svg = $this->auth->qrCodeSvg($uri);

    expect($svg)->toContain('<svg');
    expect($svg)->toContain('</svg>');
});
