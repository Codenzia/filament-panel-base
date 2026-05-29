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
 * Most-visited routes/paths over the last 7 days. Custom view widget rather
 * than a TableWidget because the underlying query is an aggregate (no
 * primary key per row) and the row count is bounded — perfect for a small
 * Blade list, no need for Filament's full table chrome.
 */
class TopPagesWidget extends Widget
{
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    protected string $view = 'filament-panel-base::filament.analytics.widgets.top-pages';

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
            ->selectRaw('COALESCE(NULLIF(route_name, ""), path) as label, COUNT(*) as views')
            ->groupBy('label')
            ->orderByDesc('views')
            ->limit($this->limit)
            ->get()
            ->map(fn ($row) => (object) [
                'label' => (string) $row->label,
                'views' => (int) $row->views,
            ]);

        return ['rows' => $rows, 'ready' => true, 'rangeLabel' => $this->getRangeLabel()];
    }
}
