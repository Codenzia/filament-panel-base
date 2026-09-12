<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Sso\ProviderRegistry;
use Illuminate\Support\Facades\Route;

/**
 * Uses the default TestCase — `sso.enabled` defaults to false.
 *
 * This is the "adopting the release changes nothing" guarantee: with the flag
 * off the module must be invisible over HTTP and on the login screen. The
 * enabled paths are covered in SsoFlowTest, which registers the routes at
 * runtime the same way the OTP API suite does.
 */
it('ships with single sign-on disabled', function (): void {
    expect(config('filament-panel-base.sso.enabled'))->toBeFalse();
});

it('registers no SSO routes while the module is disabled', function (): void {
    expect(Route::has('filament-panel-base.sso.redirect'))->toBeFalse();
    expect(Route::has('filament-panel-base.sso.callback'))->toBeFalse();
});

it('renders no login buttons while the module is disabled', function (): void {
    // Even with providers fully configured, the enabled flag alone keeps the
    // button list empty — the registry checks it before reading providers.
    config()->set('filament-panel-base.sso.providers.acme', [
        'label' => 'Acme',
        'issuer' => 'https://idp.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);

    expect(trim(view('filament-panel-base::sso.buttons')->render()))->toBe('');
});

it('offers no providers while the module is disabled', function (): void {
    $registry = app(ProviderRegistry::class);

    expect($registry->isEnabled())->toBeFalse()
        ->and($registry->all())->toBe([])
        ->and($registry->hasAny())->toBeFalse();
});
