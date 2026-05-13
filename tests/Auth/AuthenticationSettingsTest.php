<?php

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Spatie\LaravelSettings\Settings;

it('uses the auth group', function (): void {
    expect(AuthenticationSettings::group())->toBe('auth');
});

it('extends spatie settings', function (): void {
    expect(is_subclass_of(AuthenticationSettings::class, Settings::class))->toBeTrue();
});

it('declares all auth-related properties', function (): void {
    $reflection = new ReflectionClass(AuthenticationSettings::class);

    $expected = [
        'registration_mode' => 'string',
        'require_email_verification' => 'bool',
        'require_phone_verification' => 'bool',
        'credentials_mode' => 'string',
        'phone_required' => 'bool',
        'otp_driver' => 'string',
        'allowed_otp_drivers' => 'array',
        'social_providers_enabled' => 'array',
        'disposable_email_blocking' => 'bool',
        'throttle_per_minute' => 'int',
        'throttle_per_day' => 'int',
        'default_country_code' => 'string',
        'otp_code_length' => 'int',
        'otp_ttl_minutes' => 'int',
    ];

    foreach ($expected as $name => $type) {
        $prop = $reflection->getProperty($name);
        expect($prop->isPublic())->toBeTrue("{$name} must be public")
            ->and($prop->getType()->getName())->toBe($type, "{$name} type mismatch");
    }
});

it('declares sane defaults on its properties', function (): void {
    // Bypass the Spatie Settings constructor (which hits the settings repo)
    // and read the property defaults directly — this is a unit test for the
    // class shape, not an integration test for the package.
    $reflection = new ReflectionClass(AuthenticationSettings::class);

    $defaults = $reflection->getDefaultProperties();

    expect($defaults['registration_mode'])->toBe('open')
        ->and($defaults['require_email_verification'])->toBeTrue()
        ->and($defaults['require_phone_verification'])->toBeFalse()
        ->and($defaults['credentials_mode'])->toBe('email')
        ->and($defaults['otp_driver'])->toBe('email')
        ->and($defaults['otp_code_length'])->toBe(6)
        ->and($defaults['otp_ttl_minutes'])->toBe(10)
        ->and($defaults['disposable_email_blocking'])->toBeTrue();
});
