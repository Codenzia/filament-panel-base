<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetCountry
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $countryModel = config('filament-panel-base.country.model');

        // Skip if no country model is configured or in console mode
        if (! $countryModel || config('app.console_mode')) {
            return $next($request);
        }

        // Check if country is already in session
        if (! session()->has('country_id')) {
            $countryId = $this->detectCountry($request, $countryModel);
            session(['country_id' => $countryId]);
        }

        // Share the current country with all views
        $countryId = session('country_id');
        $currentCountry = $countryId ? $countryModel::find($countryId) : null;

        view()->share('currentCountry', $currentCountry);
        view()->share('availableCountries', $countryModel::published()->orderBy('order')->get());

        return $next($request);
    }

    /**
     * Detect country based on settings and IP.
     */
    private function detectCountry(Request $request, string $countryModel): ?int
    {
        $autoDetect = config('filament-panel-base.country.auto_detect', true);

        // Try to detect from IP if enabled
        if ($autoDetect) {
            $detectedCountryId = $this->detectFromIp($request, $countryModel);
            if ($detectedCountryId) {
                return $detectedCountryId;
            }
        }

        // Fall back to default country
        $defaultId = config('filament-panel-base.country.default_id');
        if ($defaultId) {
            return $defaultId;
        }

        // Last resort: get first published country
        $firstCountry = $countryModel::published()->orderBy('order')->first();

        return $firstCountry?->id;
    }

    /**
     * Detect country from IP address using geo API.
     */
    private function detectFromIp(Request $request, string $countryModel): ?int
    {
        try {
            $ip = $request->ip();

            // Skip for localhost/development
            if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
                return null;
            }

            // Check cache first
            $cacheKey = 'country_detection_' . md5($ip);
            $cachedCountryId = Cache::get($cacheKey);
            if ($cachedCountryId) {
                return $cachedCountryId;
            }

            // Call geo API for geolocation
            $geoApi = config('filament-panel-base.country.geo_api', 'https://ipapi.co/{ip}/json/');
            $url = str_replace('{ip}', $ip, $geoApi);
            $response = Http::timeout(5)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $countryCode = $data['country_code'] ?? null;

                if ($countryCode) {
                    $country = $countryModel::published()->where('code', $countryCode)->first();

                    if ($country) {
                        $cacheTtl = config('filament-panel-base.country.cache_ttl', 1440) * 60;
                        Cache::put($cacheKey, $country->id, $cacheTtl);

                        return $country->id;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Country detection failed: ' . $e->getMessage());
        }

        return null;
    }
}
