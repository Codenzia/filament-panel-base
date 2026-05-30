<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets;

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\OnlyOnAnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\ReadsAnalyticsFilters;
use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Rolling bar chart of failed logins per day for the selected range. Reads
 * `auth_events.type = login.failed`. Spikes here are usually credential
 * stuffing or a brute-force attempt against a known account.
 */
class FailedLoginsChartWidget extends ChartWidget
{
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return 'Failed logins — '.$this->getRangeLabel();
    }

    public function getDescription(): ?string
    {
        return Schema::hasTable('auth_events')
            ? 'A sustained spike usually indicates a credential-stuffing run.'
            : 'Run php artisan migrate to create the analytics tables.';
    }

    protected function getData(): array
    {
        $days = max(1, $this->getRangeDays());
        $start = Carbon::today()->subDays($days - 1);

        $labels = [];
        for ($i = 0; $i < $days; $i++) {
            $labels[] = $start->copy()->addDays($i)->format('M j');
        }

        if (! Schema::hasTable('auth_events')) {
            return [
                'datasets' => [
                    [
                        'label' => 'Failed logins',
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

        return [
            'datasets' => [
                [
                    'label' => 'Failed logins',
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
