<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

/**
 * Locale switcher endpoint shipped by codenzia/filament-panel-base.
 *
 * The locale-switcher Blade component links to `route('locale.switch', ...)`;
 * sessions the chosen locale and bounces back to the previous page so
 * SetLocale picks it up on the next request.
 *
 * Disable with `config('filament-panel-base.locale.routes.enabled') = false`
 * if the host app prefers its own implementation.
 */
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])
    ->name('locale.switch');
