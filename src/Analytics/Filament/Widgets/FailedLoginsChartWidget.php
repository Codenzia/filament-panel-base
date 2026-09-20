<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets;

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\HandlesChartEmptyState;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\OnlyOnAnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\ReadsAnalyticsFilters;
use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Rolling bar chart of failed logins per day for the selected range. Reads
 * `auth_events.type = login.failed`. Spikes here are usually credential
 * stuffing or a brute-force attempt against a known account.
 */
class FailedLoginsChartWidget extends ChartWidget
{
    use HandlesChartEmptyState;
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('filament-panel-base::analytics.failed_logins_heading', ['range' => $this->getRangeLabel()]);
    }

    public function getDescription(): ?string
    {
        return $this->hasAnalyticsTable()
            ? __('filament-panel-base::analytics.failed_logins_description')
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
            ? __('filament-panel-base::analytics.failed_logins_empty_heading')
            : __('filament-panel-base::analytics.not_migrated_heading');
    }

    public function getEmptyStateDescription(): ?string
    {
        return $this->hasAnalyticsTable()
            ? __('filament-panel-base::analytics.failed_logins_empty_description')
            : __('filament-panel-base::analytics.not_migrated_description');
    }

    public function getEmptyStateIcon(): string
    {
        return $this->hasAnalyticsTable() ? 'heroicon-o-shield-check' : 'heroicon-o-circle-stack';
    }

    protected function analyticsTable(): string
    {
        return 'auth_events';
    }

    protected function getData(): array
    {
        $days = max(1, $this->getRangeDays());
        $start = Carbon::today()->subDays($days - 1);

        $labels = [];
        for ($i = 0; $i < $days; $i++) {
            $labels[] = $start->copy()->addDays($i)->format('M j');
        }

        if (! $this->hasAnalyticsTable()) {
            return $this->supportsEmptyState()
                ? []
                : [
                    'datasets' => [
                        [
                            'label' => __('filament-panel-base::analytics.failed_logins_dataset'),
                            'data' => array_fill(0, $days, 0),
                        ],
                    ],
                    'labels' => $labels,
                ];
        }

        $rows = $this->scopeAnalyticsTenant(
            AuthEvent::query()->ofType(AuthEvent::TYPE_LOGIN_FAILED)
        )
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $key = $start->copy()->addDays($i)->toDateString();
            $values[] = (int) ($rows[$key] ?? 0);
        }

        if (array_sum($values) === 0 && $this->supportsEmptyState()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => __('filament-panel-base::analytics.failed_logins_dataset'),
                    'data' => $values,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.55)',
                    'borderColor' => 'rgb(239, 68, 68)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
