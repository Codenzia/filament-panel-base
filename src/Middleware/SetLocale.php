<?php

namespace Codenzia\FilamentPanelBase\Middleware;

use Codenzia\FilamentPanelBase\Contracts\ProvidesLocales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', $request->cookie('locale', config('app.locale')));

        $activeLanguages = $this->getActiveLocales();

        if (array_key_exists($locale, $activeLanguages)) {
            App::setLocale($locale);
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
            $locales[$code] = ['name' => $code, 'native' => $code, 'dir' => 'ltr', 'flag' => ''];
        }

        return $locales;
    }
}
