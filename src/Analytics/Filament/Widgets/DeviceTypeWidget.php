<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets;

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\OnlyOnAnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\ReadsAnalyticsFilters;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Schema;

/**
 * Doughnut chart of device types (desktop / mobile / tablet / unknown) for
 * the selected range. Reads `visits.device_type` GROUP BY.
 */
class DeviceTypeWidget extends ChartWidget
{
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return 'Device types — '.$this->getRangeLabel();
    }

    public function getDescription(): ?string
    {
        return Schema::hasTable('visits')
            ? null
            : 'Run php artisan migrate to create the analytics tables.';
    }

    protected function getData(): array
    {
        if (! Schema::hasTable('visits')) {
            return [
                'datasets' => [['data' => [], 'backgroundColor' => []]],
                'labels' => [],
            ];
        }

        $rows = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $this->getRangeStart())
            ->selectRaw('COALESCE(NULLIF(device_type, ""), \'unknown\') as device, COUNT(*) as total')
            ->groupBy('device')
            ->orderByDesc('total')
            ->pluck('total', 'device');

        // Stable colour mapping so the same device keeps the same slice
        // colour across refreshes.
        $palette = [
            'desktop' => 'rgba(59, 130, 246, 0.85)',  // blue
            'mobile' => 'rgba(16, 185, 129, 0.85)',   // green
            'tablet' => 'rgba(245, 158, 11, 0.85)',   // amber
            'unknown' => 'rgba(156, 163, 175, 0.85)', // gray
        ];

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($rows as $device => $total) {
            $labels[] = ucfirst((string) $device);
            $values[] = (int) $total;
            $colors[] = $palette[$device] ?? 'rgba(107, 114, 128, 0.85)';
        }

        return [
            'datasets' => [[
                'label' => 'Page views',
                'data' => $values,
                'backgroundColor' => $colors,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
