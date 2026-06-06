<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Filament\Pages\ManageAuthenticationSettings;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Tests\Support\OpenAuthSettingsPage;

/**
 * Structural + mount/save lifecycle tests for the admin settings page.
 * Booting a full Filament panel is out of scope — these tests drive the
 * page object directly (which is enough to exercise mount → form-fill →
 * save → settings-write without the panel chrome).
 */
beforeEach(function (): void {
    // Bind a known settings instance so mount() reads deterministic values.
    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->registration_mode = 'moderated';
    $settings->credentials_mode = 'both';
    $settings->phone_required = true;
    $settings->require_email_verification = true;
    $settings->require_phone_verification = false;
    $settings->disposable_email_blocking = true;
    $settings->default_country_code = '+962';
    $settings->otp_driver = 'whatsapp';
    $settings->allowed_otp_drivers = ['email', 'whatsapp'];
    $settings->otp_code_length = 6;
    $settings->otp_ttl_minutes = 10;
    $settings->social_providers_enabled = ['google', 'github'];
    $settings->social_email_linking = 'trust_verified';
    $settings->social_trust_verified_email = false;
    $settings->throttle_per_minute = 5;
    $settings->throttle_per_day = 50;
    app()->instance(AuthenticationSettings::class, $settings);
});

it('exposes the expected view + navigation metadata', function (): void {
    $reflection = new ReflectionClass(ManageAuthenticationSettings::class);

    expect($reflection->getProperty('view')->getDefaultValue())
        ->toBe('filament-panel-base::filament.auth.manage-authentication-settings');

    expect(ManageAuthenticationSettings::getNavigationLabel())
        ->toBe(__('filament-panel-base::auth.settings_nav_label'));

    // The admin pages live under the configurable shared group (default
    // "System"), not a hardcoded label — see ManageAuthenticationSettings::
    // getNavigationGroup() and config('filament-panel-base.admin_navigation_group').
    expect(ManageAuthenticationSettings::getNavigationGroup())
        ->toBe(config('filament-panel-base.admin_navigation_group', 'System'));
});

it('the base page is fail-closed by default (canAccess returns false)', function (): void {
    expect(ManageAuthenticationSettings::canAccess())->toBeFalse();
});

it('a host subclass that overrides canAccess opens the page', function (): void {
    // The test subclass under tests/Support mirrors the documented host
    // pattern (return true after a real auth check).
    expect(OpenAuthSettingsPage::canAccess())->toBeTrue();
});

it('hydrates form data via mount() from the AuthenticationSettings singleton', function (): void {
    $page = new OpenAuthSettingsPage;
    $page->mount();

    expect($page->data['registration_mode'])->toBe('moderated')
        ->and($page->data['credentials_mode'])->toBe('both')
        ->and($page->data['phone_required'])->toBeTrue()
        ->and($page->data['default_country_code'])->toBe('+962')
        ->and($page->data['otp_driver'])->toBe('whatsapp')
        ->and($page->data['allowed_otp_drivers'])->toBe(['email', 'whatsapp'])
        ->and($page->data['social_providers_enabled'])->toBe(['google', 'github'])
        ->and($page->data['social_email_linking'])->toBe('trust_verified')
        ->and($page->data['social_trust_verified_email'])->toBeFalse();
});

it('save() writes every field back to the settings singleton', function (): void {
    // Use a partial mock so we capture writes without needing the spatie
    // settings persistence layer to be wired in tests.
    $settings = Mockery::mock(AuthenticationSettings::class)->makePartial();
    $settings->shouldReceive('save')->once()->andReturnSelf();
    // Seed defaults the form mount() will read.
    $settings->registration_mode = 'moderated';
    $settings->credentials_mode = 'both';
    $settings->phone_required = true;
    $settings->require_email_verification = true;
    $settings->require_phone_verification = false;
    $settings->disposable_email_blocking = true;
    $settings->default_country_code = '+962';
    $settings->otp_driver = 'whatsapp';
    $settings->allowed_otp_drivers = ['email', 'whatsapp'];
    $settings->otp_code_length = 6;
    $settings->otp_ttl_minutes = 10;
    $settings->social_providers_enabled = ['google', 'github'];
    $settings->social_email_linking = 'trust_verified';
    $settings->social_trust_verified_email = false;
    $settings->throttle_per_minute = 5;
    $settings->throttle_per_day = 50;
    app()->instance(AuthenticationSettings::class, $settings);

    $page = new OpenAuthSettingsPage;
    $page->mount();

    // Mutate every category of field so save() has to round-trip them all.
    $page->data['registration_mode'] = 'open';
    $page->data['credentials_mode'] = 'phone';
    $page->data['phone_required'] = false;
    $page->data['require_email_verification'] = false;
    $page->data['default_country_code'] = '+1';
    $page->data['otp_driver'] = 'twilio';
    $page->data['allowed_otp_drivers'] = ['twilio', 'twilio', 'email']; // dup → save() dedupes
    $page->data['otp_code_length'] = 8;
    $page->data['otp_ttl_minutes'] = 15;
    $page->data['social_providers_enabled'] = ['apple', 'github', 'apple']; // dup → dedupes
    $page->data['social_email_linking'] = 'require_login';
    $page->data['social_trust_verified_email'] = true;
    $page->data['throttle_per_minute'] = 9;
    $page->data['throttle_per_day'] = 90;

    $page->save();

    $written = app(AuthenticationSettings::class);

    expect($written->registration_mode)->toBe('open')
        ->and($written->credentials_mode)->toBe('phone')
        ->and($written->phone_required)->toBeFalse()
        ->and($written->require_email_verification)->toBeFalse()
        ->and($written->default_country_code)->toBe('+1')
        ->and($written->otp_driver)->toBe('twilio')
        ->and($written->allowed_otp_drivers)->toBe(['twilio', 'email'])
        ->and($written->otp_code_length)->toBe(8)
        ->and($written->otp_ttl_minutes)->toBe(15)
        ->and($written->social_providers_enabled)->toBe(['apple', 'github'])
        ->and($written->social_email_linking)->toBe('require_login')
        ->and($written->social_trust_verified_email)->toBeTrue()
        ->and($written->throttle_per_minute)->toBe(9)
        ->and($written->throttle_per_day)->toBe(90);
});
