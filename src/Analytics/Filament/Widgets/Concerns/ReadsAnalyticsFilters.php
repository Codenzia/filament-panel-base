<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Shared filter + tenant-scope helpers for analytics widgets.
 *
 * Filter source: AnalyticsPage::filtersForm() exposes `range` (24h / 7d / 30d / 90d).
 * The Filament `InteractsWithPageFilters` trait injects the page's filter
 * array into each widget as `$this->pageFilters`. We translate that into
 * convenient Carbon boundaries and a tenant scope.
 *
 * Tenant scope: when the panel uses Filament tenancy (`filament()->getTenant()`
 * returns a model), the helpers filter by `tenant_id` + `tenant_type` so one
 * tenant never sees another's traffic. Single-tenant panels: no-op.
 */
trait ReadsAnalyticsFilters
{
    use InteractsWithPageFilters;

    protected function getRangeKey(): string
    {
        return $this->pageFilters['range'] ?? '7d';
    }

    protected function getRangeStart(): Carbon
    {
        return match ($this->getRangeKey()) {
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            default => Carbon::now()->subDays(7),
        };
    }

    protected function getRangeDays(): int
    {
        return match ($this->getRangeKey()) {
            '24h' => 1,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };
    }

    protected function getRangeLabel(): string
    {
        return match ($this->getRangeKey()) {
            '24h' => 'last 24 hours',
            '30d' => 'last 30 days',
            '90d' => 'last 90 days',
            default => 'last 7 days',
        };
    }

    /**
     * Apply tenant scoping to an analytics query. No-op when the panel is
     * single-tenant or when called outside a Filament request.
     *
     * @template TBuilder of Builder|QueryBuilder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    protected function scopeAnalyticsTenant(
        Builder|QueryBuilder $query,
        string $idColumn = 'tenant_id',
        string $typeColumn = 'tenant_type',
    ): Builder|QueryBuilder {
        $tenant = $this->currentTenant();

        if ($tenant === null) {
            return $query;
        }

        return $query
            ->where($idColumn, (string) $tenant->getKey())
            ->where($typeColumn, $tenant::class);
    }

    private function currentTenant(): ?Model
    {
        if (! function_exists('filament')) {
            return null;
        }

        try {
            $tenant = filament()->getTenant();

            return $tenant instanceof Model ? $tenant : null;
        } catch (Throwable) {
            return null;
        }
    }
}
