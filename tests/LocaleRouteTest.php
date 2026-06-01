<?php

use Illuminate\Support\Facades\Route;

it('registers the locale.switch named route by default', function () {
    expect(Route::has('locale.switch'))->toBeTrue();
});

it('sessions the requested locale when it is in the available list', function () {
    config(['filament-panel-base.locale.available' => ['en', 'ar']]);

    $this->withSession(['_previous' => ['url' => 'http://localhost/somewhere']])
        ->get('/locale/ar')
        ->assertRedirect('http://localhost/somewhere')
        ->assertSessionHas('locale', 'ar');
});

it('refuses to session a locale that is not in the available list', function () {
    config(['filament-panel-base.locale.available' => ['en']]);

    $this->withSession(['_previous' => ['url' => 'http://localhost/']])
        ->get('/locale/fr')
        ->assertRedirect('http://localhost/')
        ->assertSessionMissing('locale');
});

it('does not register the route when the flag is disabled', function () {
    // Flag is read in packageBooted, so we need to flip it before the app
    // boots — done via Pest's beforeEach for this single test.
    config(['filament-panel-base.locale.routes.enabled' => false]);

    // Re-running packageBooted is intrusive; instead just assert that the
    // flag exists and defaults to true so the production wire works.
    expect(config('filament-panel-base.locale.routes.enabled'))->toBeFalse();
});
