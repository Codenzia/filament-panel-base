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

        // back() trusts the (header-controlled) Referer. Pin to a same-origin
        // target so the switcher can't be used as an open redirect.
        $back = url()->previous();

        if (! str_starts_with($back, url('/'))) {
            $back = url('/');
        }

        return redirect()->to($back);
    }
}
