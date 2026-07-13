<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets;

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\OnlyOnAnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\ReadsAnalyticsFilters;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Rolling line chart of human page views per day for the selected date range.
 * Queries the raw `visits` table directly and buckets in-query.
 *
 * For 24h ranges we bucket by hour; for multi-day ranges we bucket by day.
 */
class VisitorsChartWidget extends ChartWidget
{
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return 'Page views — '.$this->getRangeLabel();
    }

    public function getDescription(): ?string
    {
        return Schema::hasTable('visits')
            ? null
            : 'Run php artisan migrate to create the analytics tables.';
    }

    protected function getData(): array
    {
        $useHourlyBuckets = $this->getRangeKey() === '24h';

        return $useHourlyBuckets
            ? $this->hourlyData()
            : $this->dailyData();
    }

    /** @return array<string, mixed> */
    private function dailyData(): array
    {
        $days = $this->getRangeDays();
        $start = Carbon::today()->subDays($days - 1);

        $labels = [];
        for ($i = 0; $i < $days; $i++) {
            $labels[] = $start->copy()->addDays($i)->format('M j');
        }

        if (! Schema::hasTable('visits')) {
            return $this->emptyDataset('Page views', $labels);
        }

        $rows = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $key = $start->copy()->addDays($i)->toDateString();
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return [
            'datasets' => [[
                'label' => 'Page views',
                'data' => $values,
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $labels,
        ];
    }

    /** @return array<string, mixed> */
    private function hourlyData(): array
    {
        $start = Carbon::now()->subHours(23)->startOfHour();

        $labels = [];
        for ($i = 0; $i < 24; $i++) {
            $labels[] = $start->copy()->addHours($i)->format('H:00');
        }

        if (! Schema::hasTable('visits')) {
            return $this->emptyDataset('Page views', $labels);
        }

        // SQLite uses strftime('%Y-%m-%d %H'); MySQL uses DATE_FORMAT. Both
        // accept the same buckets via the portable DATE() + HOUR() pair, but
        // calling them separately keeps the query driver-agnostic.
        $rows = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($row) => Carbon::parse($row->created_at)->startOfHour()->format('Y-m-d H'))
            ->map(fn ($group) => $group->count());

        $values = [];
        for ($i = 0; $i < 24; $i++) {
            $key = $start->copy()->addHours($i)->format('Y-m-d H');
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return [
            'datasets' => [[
                'label' => 'Page views',
                'data' => $values,
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $labels,
        ];
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<string, mixed>
     */
    private function emptyDataset(string $label, array $labels): array
    {
        return [
            'datasets' => [[
                'label' => $label,
                'data' => array_fill(0, count($labels), 0),
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
