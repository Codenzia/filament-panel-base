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
        $activeLanguages = $this->getActiveLocales();

        $this->seedSessionFromUser($request, $activeLanguages);

        $locale = session('locale', $request->cookie('locale', config('app.locale')));

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
     * Seed the session from the signed-in user's stored locale.
     *
     * Session → cookie → app default leaves an account preference unread, so a
     * user whose row says Arabic still lands on an English panel the first time
     * they sign in on a new device — which is why hosts kept writing their own
     * middleware to copy the column into the session.
     *
     * Deliberately narrow: it only fires when the host names a column via
     * `locale.user_attribute` (null, the default, is the old behaviour exactly),
     * only for an authenticated user, only when the session carries no locale of
     * its own, and only for a value in the active list. An explicit in-session
     * choice therefore always wins — including the user switching language and
     * staying switched — and guests are untouched.
     *
     * @param  array<string, mixed>  $activeLanguages
     */
    protected function seedSessionFromUser(Request $request, array $activeLanguages): void
    {
        $attribute = config('filament-panel-base.locale.user_attribute');

        if (! is_string($attribute) || $attribute === '') {
            return;
        }

        if (! $request->hasSession() || $request->session()->has('locale')) {
            return;
        }

        $user = $request->user();

        if ($user === null) {
            return;
        }

        $preferred = $user->{$attribute} ?? null;

        if (! is_string($preferred) || $preferred === '') {
            return;
        }

        if (! array_key_exists($preferred, $activeLanguages)) {
            return;
        }

        $request->session()->put('locale', $preferred);
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
