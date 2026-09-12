<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Http\Middleware\ThrottleAuth;
use Codenzia\FilamentPanelBase\Sso\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

/**
 * OIDC single sign-on routes, registered only while
 * `config('filament-panel-base.sso.enabled')` is true.
 *
 * Both endpoints are real HTTP GETs with a backend cost (discovery, token
 * exchange), so they carry the same ThrottleAuth middleware the OAuth routes
 * use. The callback URI registered with the identity provider is
 * {app_url}/{prefix}/{provider}/callback.
 */
Route::middleware([ThrottleAuth::class])->group(function (): void {
    Route::get('/{provider}/redirect', [SsoController::class, 'redirect'])->name('redirect');
    Route::get('/{provider}/callback', [SsoController::class, 'callback'])->name('callback');
});
