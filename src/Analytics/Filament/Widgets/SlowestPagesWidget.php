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
 * Top 10 slowest routes by AVG(duration_ms) over the selected range.
 * Requires routes to have at least 5 samples to avoid one cold-start
 * outlier dominating the list.
 */
class SlowestPagesWidget extends Widget
{
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    protected string $view = 'filament-panel-base::filament.analytics.widgets.slowest-pages';

    protected int|string|array $columnSpan = 1;

    public int $limit = 10;

    public int $minSamples = 5;

    /** @return array{rows: Collection, ready: bool, rangeLabel: string} */
    protected function getViewData(): array
    {
        if (! Schema::hasTable('visits')) {
            return ['rows' => collect(), 'ready' => false, 'rangeLabel' => $this->getRangeLabel()];
        }

        $rows = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $this->getRangeStart())
            ->whereNotNull('duration_ms')
            ->selectRaw('COALESCE(NULLIF(route_name, ""), path) as label, AVG(duration_ms) as avg_ms, COUNT(*) as samples')
            ->groupBy('label')
            ->havingRaw('COUNT(*) >= ?', [$this->minSamples])
            ->orderByDesc('avg_ms')
            ->limit($this->limit)
            ->get()
            ->map(fn ($row) => (object) [
                'label' => (string) $row->label,
                'avgMs' => (int) round((float) $row->avg_ms),
                'samples' => (int) $row->samples,
            ]);

        return ['rows' => $rows, 'ready' => true, 'rangeLabel' => $this->getRangeLabel()];
    }
}
