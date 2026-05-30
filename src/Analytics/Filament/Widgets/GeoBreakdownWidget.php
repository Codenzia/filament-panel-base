<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets;

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\OnlyOnAnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\ReadsAnalyticsFilters;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Country breakdown of human page views in the last 7 days. Renders the
 * flag (via the package's bundled flag-icons CSS) next to the ISO code +
 * view count. Sorted descending; capped at `$limit` countries.
 */
class GeoBreakdownWidget extends Widget
{
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    protected string $view = 'filament-panel-base::filament.analytics.widgets.geo-breakdown';

    protected int|string|array $columnSpan = 1;

    public int $limit = 10;

    /** @return array{rows: Collection, ready: bool, rangeLabel: string} */
    protected function getViewData(): array
    {
        if (! Schema::hasTable('visits')) {
            return ['rows' => collect(), 'ready' => false, 'rangeLabel' => $this->getRangeLabel()];
        }

        $rows = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $this->getRangeStart())
            ->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) as views')
            ->groupBy('country_code')
            ->orderByDesc('views')
            ->limit($this->limit)
            ->get()
            ->map(fn ($row) => (object) [
                'country' => strtoupper((string) $row->country_code),
                'views' => (int) $row->views,
            ]);

        return ['rows' => $rows, 'ready' => true, 'rangeLabel' => $this->getRangeLabel()];
    }
}
