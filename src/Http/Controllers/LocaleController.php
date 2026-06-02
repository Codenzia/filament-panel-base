<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Http\Controllers;

use Codenzia\FilamentPanelBase\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sessions the requested locale (validated against the active locales
 * resolved by SetLocale) and returns to the previous page.
 *
 * Backs the `locale.switch` named route consumed by panel-base's
 * locale-switcher Blade component (default `$switchRoute` prop).
 * SetLocale picks the new locale up on the next request and syncs
 * `spatie_translatable_active_locale` accordingly.
 */
class LocaleController
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (array_key_exists($locale, SetLocale::getLocales())) {
            $request->session()->put('locale', $locale);
        }

        return redirect()->back();
    }
}
