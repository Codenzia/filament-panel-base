<?php

it('publishes default config values', function () {
    expect(config('filament-panel-base.admin_role'))->toBe('super_admin')
        ->and(config('filament-panel-base.user_model'))->toBe(\App\Models\User::class)
        ->and(config('filament-panel-base.panels'))->toBe(['admin', 'dashboard']);
});

it('has locale defaults', function () {
    $locale = config('filament-panel-base.locale');

    expect($locale)->toBeArray()
        ->and($locale['provider'])->toBeNull()
        ->and($locale['available'])->toBe(['en'])
        ->and($locale['detection_order'])->toBe(['session', 'cookie', 'config']);
});

it('has country defaults', function () {
    $country = config('filament-panel-base.country');

    expect($country)->toBeArray()
        ->and($country['auto_detect'])->toBeTrue()
        ->and($country['default_id'])->toBeNull()
        ->and($country['model'])->toBeNull()
        ->and($country['cache_ttl'])->toBe(1440);
});

it('has currency defaults', function () {
    $currency = config('filament-panel-base.currency');

    expect($currency)->toBeArray()
        ->and($currency['model'])->toBeNull()
        ->and($currency['virtual_usd'])->toBeTrue();
});

it('has contact validation defaults', function () {
    $validation = config('filament-panel-base.contact_validation');

    expect($validation)->toBeArray()
        ->and($validation['require_whatsapp'])->toBeFalse()
        ->and($validation['allow_email_alternative'])->toBeTrue();
});

it('has default color palette', function () {
    $colors = config('filament-panel-base.colors');

    expect($colors)->toBeArray()
        ->and($colors)->toHaveKey('primary')
        ->and($colors)->toHaveKey('secondary')
        ->and($colors)->toHaveKey('danger')
        ->and($colors)->toHaveKey('warning')
        ->and($colors)->toHaveKey('success')
        ->and($colors)->toHaveKey('info');
});

it('has no settings class by default', function () {
    expect(config('filament-panel-base.settings_class'))->toBeNull();
});
