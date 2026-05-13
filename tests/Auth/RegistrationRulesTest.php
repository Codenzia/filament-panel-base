<?php

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Auth\Validation\RegistrationRules;

it('builds email-only rules by default', function (): void {
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    $settings->credentials_mode = 'email';

    $rules = RegistrationRules::build($settings);

    expect($rules)->toHaveKeys(['name', 'email', 'phone', 'password'])
        ->and($rules['email'])->toContain('required')
        ->and($rules['phone'])->toContain('nullable');
});

it('builds phone-only rules', function (): void {
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    $settings->credentials_mode = 'phone';

    $rules = RegistrationRules::build($settings);

    expect($rules['phone'])->toContain('required')
        ->and($rules['email'])->toContain('nullable');
});

it('requires both when credentials_mode is "both" and phone_required is true', function (): void {
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    $settings->credentials_mode = 'both';
    $settings->phone_required = true;

    $rules = RegistrationRules::build($settings);

    expect($rules['email'])->toContain('required')
        ->and($rules['phone'])->toContain('required');
});

it('keeps phone optional when credentials_mode is "both" but phone_required is false', function (): void {
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    $settings->credentials_mode = 'both';
    $settings->phone_required = false;

    $rules = RegistrationRules::build($settings);

    expect($rules['email'])->toContain('required')
        ->and($rules['phone'])->toContain('nullable');
});

it('always requires name and password', function (): void {
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();

    $rules = RegistrationRules::build($settings);

    expect($rules['name'])->toContain('required')
        ->and($rules['password'])->toContain('required')
        ->and($rules['password'])->toContain('confirmed');
});
