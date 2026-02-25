<?php

namespace Codenzia\FilamentPanelBase\Contracts;

/**
 * Contract for Currency models used by the SetCurrency middleware.
 *
 * Implement this interface on your Currency model.
 */
interface ProvidesCurrencies
{
    /**
     * Get the currency code (e.g., 'USD', 'EUR').
     */
    public function getCodeAttribute(): string;

    /**
     * Get the currency symbol (e.g., '$', '€').
     */
    public function getSymbolAttribute(): string;
}
