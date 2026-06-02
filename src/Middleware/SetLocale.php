<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Middleware;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Codenzia\FilamentPanelBase\Contracts\ProvidesLocales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', $request->cookie('locale', config('app.locale')));

        $activeLanguages = $this->getActiveLocales();

        if (array_key_exists($locale, $activeLanguages)) {
            App::setLocale($locale);

            // Carbon (date diffs, ->translatedFormat(), ->diffForHumans()) and
            // the Number helper (currency, ordinals, percentages) both keep
            // their own per-process locale. Forgetting either is the classic
            // "UI is Arabic but dates/numbers still print English" bug.
            Carbon::setLocale($locale);
            CarbonImmutable::setLocale($locale);

            if (class_exists(Number::class)) {
                Number::useLocale($locale);
            }

            // Sync the translatable content locale whenever the UI locale changes,
            // so the content editor defaults to the same language as the UI.
            // A user's explicit choice within the same UI locale is preserved.
            if (session('_ui_locale') !== $locale) {
                session()->put('spatie_translatable_active_locale', $locale);
                session()->put('_ui_locale', $locale);
            }
        }

        return $next($request);
    }

    /**
     * Get active locales from the configured provider or config fallback.
     */
    public static function getLocales(): array
    {
        return (new static)->getActiveLocales();
    }

    /**
     * Resolve active locales from provider class or config.
     */
    protected function getActiveLocales(): array
    {
        $providerClass = config('filament-panel-base.locale.provider');

        if ($providerClass && class_exists($providerClass)) {
            $provider = new $providerClass;

            if ($provider instanceof ProvidesLocales) {
                return $providerClass::getActive();
            }
        }

        // Fallback to config-based locales
        $available = config('filament-panel-base.locale.available', ['en']);

        $locales = [];
        foreach ($available as $code) {
            $locales[$code] = [
                'name' => $code,
                'native' => $code,
                'dir' => self::isRtlLocale($code) ? 'rtl' : 'ltr',
                'flag' => '',
            ];
        }

        return $locales;
    }

    /**
     * Static list of canonical RTL locale codes. Keeps the locale-switcher
     * fallback honest: declaring 'ar' should produce dir=rtl in the
     * dropdown payload without forcing the host to wire a ProvidesLocales
     * implementation.
     */
    public static function isRtlLocale(string $code): bool
    {
        return in_array(strtolower($code), [
            'ar', 'he', 'fa', 'ur', 'ps', 'sd', 'yi', 'ku', 'dv',
        ], true);
    }
}
