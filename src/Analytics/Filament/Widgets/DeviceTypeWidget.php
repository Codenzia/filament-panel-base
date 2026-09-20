<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets;

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\HandlesChartEmptyState;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\OnlyOnAnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\ReadsAnalyticsFilters;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Lang;

/**
 * Doughnut chart of device types (desktop / mobile / tablet / unknown) for
 * the selected range. Reads `visits.device_type` GROUP BY.
 */
class DeviceTypeWidget extends ChartWidget
{
    use HandlesChartEmptyState;
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return __('filament-panel-base::analytics.devices_heading', ['range' => $this->getRangeLabel()]);
    }

    public function getDescription(): ?string
    {
        return $this->hasAnalyticsTable()
            ? null
            : __('filament-panel-base::analytics.not_migrated_description');
    }

    /**
     * Filament renders this heading/description/icon trio in place of the
     * chart canvas whenever getData() returns an empty array. Supported from
     * v4.13 / v5.x only — see HandlesChartEmptyState for what older versions
     * get instead.
     */
    public function getEmptyStateHeading(): string
    {
        return $this->hasAnalyticsTable()
            ? __('filament-panel-base::analytics.devices_empty_heading')
            : __('filament-panel-base::analytics.not_migrated_heading');
    }

    public function getEmptyStateDescription(): ?string
    {
        return $this->hasAnalyticsTable()
            ? __('filament-panel-base::analytics.devices_empty_description')
            : __('filament-panel-base::analytics.not_migrated_description');
    }

    public function getEmptyStateIcon(): string
    {
        return $this->hasAnalyticsTable() ? 'heroicon-o-device-phone-mobile' : 'heroicon-o-circle-stack';
    }

    protected function analyticsTable(): string
    {
        return 'visits';
    }

    protected function getData(): array
    {
        if (! $this->hasAnalyticsTable()) {
            return $this->supportsEmptyState()
                ? []
                : [
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

        if ($rows->isEmpty() && $this->supportsEmptyState()) {
            return [];
        }

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
            $labels[] = $this->deviceLabel((string) $device);
            $values[] = (int) $total;
            $colors[] = $palette[$device] ?? 'rgba(107, 114, 128, 0.85)';
        }

        return [
            'datasets' => [[
                'label' => __('filament-panel-base::analytics.devices_dataset'),
                'data' => $values,
                'backgroundColor' => $colors,
            ]],
            'labels' => $labels,
        ];
    }

    /**
     * Translated slice label for a known device type, falling back to the raw
     * column value for anything a host's own parser writes.
     */
    protected function deviceLabel(string $device): string
    {
        $key = 'filament-panel-base::analytics.device_'.$device;

        return Lang::has($key) ? __($key) : ucfirst($device);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
