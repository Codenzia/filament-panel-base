<?php

use Codenzia\FilamentPanelBase\Settings\RegistrationSettings;
use Spatie\LaravelSettings\Settings;

it('uses the registration group', function () {
    expect(RegistrationSettings::group())->toBe('registration');
});

it('extends spatie settings', function () {
    expect(is_subclass_of(RegistrationSettings::class, Settings::class))->toBeTrue();
});

it('declares expected public properties', function () {
    $reflection = new ReflectionClass(RegistrationSettings::class);

    $registrationMode = $reflection->getProperty('registration_mode');
    $emailVerification = $reflection->getProperty('require_email_verification');

    expect($registrationMode->isPublic())->toBeTrue()
        ->and($registrationMode->getType()->getName())->toBe('string')
        ->and($emailVerification->isPublic())->toBeTrue()
        ->and($emailVerification->getType()->getName())->toBe('bool');
});
