<?php

use Codenzia\FilamentPanelBase\Auth\AuthenticationPlugin;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;

it('starts disabled', function (): void {
    $plugin = new AuthenticationPlugin;
    expect($plugin->isEnabled())->toBeFalse();
});

it('captures credentials mode', function (): void {
    $plugin = (new AuthenticationPlugin)->credentials('email', 'phone');
    expect($plugin->getOverrides()['credentials_mode'])->toBe('both');

    $plugin = (new AuthenticationPlugin)->credentials('phone');
    expect($plugin->getOverrides()['credentials_mode'])->toBe('phone');

    $plugin = (new AuthenticationPlugin)->credentials('email');
    expect($plugin->getOverrides()['credentials_mode'])->toBe('email');
});

it('toggles moderation', function (): void {
    $plugin = (new AuthenticationPlugin)->moderation();
    expect($plugin->getOverrides()['registration_mode'])->toBe('moderated');

    $plugin = (new AuthenticationPlugin)->moderation(false);
    expect($plugin->getOverrides()['registration_mode'])->toBe('open');
});

it('captures verification driver and allowed list', function (): void {
    $plugin = (new AuthenticationPlugin)->verification(driver: 'whatsapp', allowed: ['whatsapp', 'email']);
    expect($plugin->getOverrides()['otp_driver'])->toBe('whatsapp')
        ->and($plugin->getOverrides()['allowed_otp_drivers'])->toBe(['whatsapp', 'email']);
});

it('captures social providers', function (): void {
    $plugin = (new AuthenticationPlugin)->social(['google', 'facebook', 'google']);
    expect($plugin->getOverrides()['social_providers_enabled'])->toBe(['google', 'facebook']);
});

it('captures the social email-linking policy and normalises case', function (): void {
    $plugin = (new AuthenticationPlugin)->socialEmailLinking('Trust_Verified');
    expect($plugin->getOverrides()['social_email_linking'])->toBe('trust_verified');
});

it('rejects an unknown social email-linking policy', function (): void {
    expect(fn () => (new AuthenticationPlugin)->socialEmailLinking('whatever'))
        ->toThrow(InvalidArgumentException::class);
});

it('captures the social-trust-verified-email flag', function (): void {
    $plugin = (new AuthenticationPlugin)->socialTrustVerifiedEmail(false);
    expect($plugin->getOverrides()['social_trust_verified_email'])->toBeFalse();

    $plugin = (new AuthenticationPlugin)->socialTrustVerifiedEmail();
    expect($plugin->getOverrides()['social_trust_verified_email'])->toBeTrue();
});

it('apply writes social policy + trust flag through to AuthenticationSettings', function (): void {
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    // Initialise the defaults explicitly — Spatie settings constructor is bypassed by newInstanceWithoutConstructor.
    $settings->social_email_linking = 'require_login';
    $settings->social_trust_verified_email = true;

    (new AuthenticationPlugin)
        ->socialEmailLinking('trust_verified')
        ->socialTrustVerifiedEmail(false)
        ->enable()
        ->apply($settings);

    expect($settings->social_email_linking)->toBe('trust_verified')
        ->and($settings->social_trust_verified_email)->toBeFalse();
});

it('captures filament panel page flags', function (): void {
    $plugin = (new AuthenticationPlugin)->filamentPanelPages(login: true, register: true);
    expect($plugin->hasFilamentPanelPages())->toBeTrue()
        ->and($plugin->hasFilamentLoginPage())->toBeTrue()
        ->and($plugin->hasFilamentRegisterPage())->toBeTrue();

    $plugin = new AuthenticationPlugin;
    expect($plugin->hasFilamentPanelPages())->toBeFalse();
});

it('apply mutates the AuthenticationSettings singleton when enabled', function (): void {
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    expect($settings->credentials_mode)->toBe('email');

    $plugin = (new AuthenticationPlugin)->credentials('phone')->enable();
    $plugin->apply($settings);

    expect($settings->credentials_mode)->toBe('phone');
});

it('apply is a no-op when not enabled', function (): void {
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    expect($settings->credentials_mode)->toBe('email');

    (new AuthenticationPlugin)->credentials('phone')->apply($settings);

    expect($settings->credentials_mode)->toBe('email');
});
