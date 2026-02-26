<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currencyModel = config('filament-panel-base.currency.model');
        $countryModel = config('filament-panel-base.country.model');

        // Skip if no currency model is configured or in console mode
        if (! $currencyModel || config('app.console_mode')) {
            return $next($request);
        }

        // Check if currency is already in session
        if (! session()->has('currency_id')) {
            // Default to 'auto' which means use country's currency
            session(['currency_id' => 'auto']);
        }

        // Share the current currency and available currencies with all views
        $currencyId = session('currency_id');
        $currentCurrency = $this->getCurrentCurrency($currencyId, $currencyModel, $countryModel);

        view()->share('currentCurrency', $currentCurrency);
        view()->share('currentCurrencyMode', $currencyId);
        view()->share('availableCurrencies', $this->getAvailableCurrencies($currencyModel, $countryModel));

        return $next($request);
    }

    /**
     * Get current currency based on mode.
     */
    private function getCurrentCurrency(string|int|null $currencyId, string $currencyModel, ?string $countryModel): ?object
    {
        // If auto mode, get country's currency
        if ($currencyId === 'auto' || $currencyId === null) {
            return $this->getCountryCurrency($currencyModel, $countryModel);
        }

        // Handle virtual USD
        if ($currencyId === 'usd_virtual') {
            return $this->getVirtualUsd($currencyModel);
        }

        // Get actual currency from database
        $currency = $currencyModel::find($currencyId);

        // Fallback if currency not found
        if (! $currency) {
            return $this->getCountryCurrency($currencyModel, $countryModel);
        }

        return $currency;
    }

    /**
     * Get country's default currency.
     */
    private function getCountryCurrency(string $currencyModel, ?string $countryModel): ?object
    {
        if ($countryModel) {
            $countryId = session('country_id');

            if ($countryId) {
                $country = $countryModel::with('currency')->find($countryId);
                if ($country && $country->currency) {
                    return $country->currency;
                }
            }
        }

        // Fall back to system default
        return $currencyModel::where('is_default', true)->first()
            ?? $currencyModel::orderBy('order')->first();
    }

    /**
     * Get virtual USD currency.
     */
    private function getVirtualUsd(string $currencyModel): object
    {
        $usd = new $currencyModel([
            'title' => 'US Dollar',
            'symbol' => '$',
            'code' => 'USD',
            'is_prefix_symbol' => true,
            'exchange_rate' => 1.0,
        ]);
        $usd->id = 'usd_virtual';
        $usd->exists = false;

        return $usd;
    }

    /**
     * Get available currencies for dropdown.
     * Structure: Auto (country default) -> USD -> All currencies
     */
    private function getAvailableCurrencies(string $currencyModel, ?string $countryModel): array
    {
        $list = [];

        // 1. Add "Default for country" option
        $countryId = session('country_id');
        $country = ($countryModel && $countryId) ? $countryModel::find($countryId) : null;
        $countryCurrency = $country?->currency;

        $list[] = (object) [
            'id' => 'auto',
            'code' => 'AUTO',
            'symbol' => $countryCurrency?->symbol ?? '$',
            'title' => __('Default for :country', ['country' => $country?->name ?? __('Country')]),
            'is_auto' => true,
        ];

        // 2. Add USD (virtual if not in database)
        if (config('filament-panel-base.currency.virtual_usd', true)) {
            $dbUsd = $currencyModel::where('code', 'USD')->first();
            if ($dbUsd) {
                $list[] = $dbUsd;
            } else {
                $list[] = $this->getVirtualUsd($currencyModel);
            }
        }

        // 3. Add all database currencies (excluding USD if already added)
        $currencies = $currencyModel::orderBy('order')
            ->orderBy('title')
            ->get();

        foreach ($currencies as $currency) {
            if ($currency->code !== 'USD') {
                $list[] = $currency;
            }
        }

        return $list;
    }
}
