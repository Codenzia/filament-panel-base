<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
use Codenzia\FilamentPanelBase\TwoFactor\Filament\Pages\TwoFactorChallengePage;

it('opts in via withFilamentTwoFactorChallengePage()', function (): void {
    $plugin = new FilamentPanelBasePlugin;

    expect($plugin->hasFilamentTwoFactorChallengePage())->toBeFalse()
        ->and($plugin->getFilamentTwoFactorChallengePageClass())->toBeNull();

    $plugin->withFilamentTwoFactorChallengePage();

    expect($plugin->hasFilamentTwoFactorChallengePage())->toBeTrue()
        ->and($plugin->getFilamentTwoFactorChallengePageClass())
        ->toBe(TwoFactorChallengePage::class);
});

it('accepts a host subclass for the challenge page', function (): void {
    // Hosts that want to render the challenge in a custom layout extend the
    // SimplePage subclass and hand it to withFilamentTwoFactorChallengePage().
    $plugin = (new FilamentPanelBasePlugin)
        ->withFilamentTwoFactorChallengePage(TwoFactorChallengePage::class);

    expect($plugin->getFilamentTwoFactorChallengePageClass())
        ->toBe(TwoFactorChallengePage::class);
});

it('does not route-register the challenge via Filament Page::registerRoutes', function (): void {
    // Regression for v0.3.0: the plugin previously registered the challenge
    // page through $panel->pages([...]), which calls static::registerRoutes()
    // on every entry. TwoFactorChallengePage extends SimplePage — which
    // (unlike regular Page) does NOT use HasRoutes — so the boot fataled
    // with "Method ...::registerRoutes does not exist". The fix routes
    // through $panel->routes() instead, mounting the page at
    // /{panel-path}/two-factor-challenge without touching the page-class
    // route machinery.
    //
    // The two visible signals that the bug is fixed:
    //   1. TwoFactorChallengePage does NOT have a static registerRoutes()
    //      (we removed the attempt to backport HasRoutes onto SimplePage).
    //   2. The plugin's apply() path used by Filament's panel-boot
    //      hooks does not call $panel->pages([TwoFactorChallengePage::class]).
    expect(method_exists(TwoFactorChallengePage::class, 'registerRoutes'))->toBeFalse();
});
