<?php

use Codenzia\FilamentPanelBase\Auth\AuthenticationPlugin;
use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;

it('starts with the in-panel adapter disabled', function (): void {
    $plugin = new FilamentPanelBasePlugin;

    expect($plugin->hasFilamentAuthLogin())->toBeFalse()
        ->and($plugin->hasFilamentAuthRegister())->toBeFalse();
});

it('enables the in-panel login adapter via the top-level fluent method', function (): void {
    $plugin = (new FilamentPanelBasePlugin)->withFilamentAuthPages(login: true);

    expect($plugin->hasFilamentAuthLogin())->toBeTrue()
        ->and($plugin->hasFilamentAuthRegister())->toBeFalse();
});

it('enables the in-panel register adapter independently', function (): void {
    $plugin = (new FilamentPanelBasePlugin)->withFilamentAuthPages(register: true);

    expect($plugin->hasFilamentAuthLogin())->toBeFalse()
        ->and($plugin->hasFilamentAuthRegister())->toBeTrue();
});

it('enables both adapters when both flags are passed', function (): void {
    $plugin = (new FilamentPanelBasePlugin)->withFilamentAuthPages(login: true, register: true);

    expect($plugin->hasFilamentAuthLogin())->toBeTrue()
        ->and($plugin->hasFilamentAuthRegister())->toBeTrue();
});

it('does not require withAuthentication() to enable in-panel adapters', function (): void {
    // The whole point of this method: panel providers can wire the
    // adapter without triggering settings load at boot.
    $plugin = (new FilamentPanelBasePlugin)->withFilamentAuthPages(login: true);

    expect($plugin->isAuthenticationEnabled())->toBeFalse()
        ->and($plugin->getAuthentication())->toBeNull()
        ->and($plugin->hasFilamentAuthLogin())->toBeTrue();
});

it('still honours the legacy AuthenticationPlugin::filamentPanelPages path', function (): void {
    // Backward compatibility — the deprecated route through
    // withAuthentication()->filamentPanelPages() must keep working until
    // it's removed in a major bump.
    $auth = (new AuthenticationPlugin)->filamentPanelPages(login: true, register: true);

    expect($auth->hasFilamentLoginPage())->toBeTrue()
        ->and($auth->hasFilamentRegisterPage())->toBeTrue()
        ->and($auth->hasFilamentPanelPages())->toBeTrue();
});
