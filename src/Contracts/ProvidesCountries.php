<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contract for Country models used by the SetCountry middleware.
 *
 * Implement this interface on your Country model.
 */
interface ProvidesCountries
{
    /**
     * Scope query to published/active countries.
     */
    public function scopePublished(Builder $query): Builder;

    /**
     * Get the country's currency relationship.
     */
    public function currency(): BelongsTo;
}
