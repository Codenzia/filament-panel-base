<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets;

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\OnlyOnAnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\ReadsAnalyticsFilters;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Three at-a-glance stats over the selected range:
 *   - 5xx error count + a per-day sparkline (catches deploy regressions)
 *   - 404 count (broken links + scanner activity)
 *   - Overall error rate as a percentage of all human visits
 */
class ErrorRateSparklineWidget extends StatsOverviewWidget
{
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    public function getHeading(): ?string
    {
        return 'Errors — '.$this->getRangeLabel();
    }

    public function getPollingInterval(): ?string
    {
        return '60s';
    }

    protected function getStats(): array
    {
        if (! Schema::hasTable('visits')) {
            return [
                Stat::make('Errors', 'Not ready')
                    ->description('Run php artisan migrate.')
                    ->color('gray'),
            ];
        }

        $start = $this->getRangeStart();
        $days = $this->getRangeDays();

        $totalHits = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $start)
            ->count();

        $server5xx = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $start)
            ->where('status', '>=', 500)
            ->count();

        $notFound = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $start)
            ->where('status', 404)
            ->count();

        $errorRate = $totalHits > 0
            ? round(($server5xx / $totalHits) * 100, 2)
            : 0.0;

        return [
            Stat::make('5xx errors', number_format($server5xx))
                ->description($server5xx > 0 ? 'Server errors hit users' : 'No server errors')
                ->descriptionIcon($server5xx > 0 ? 'heroicon-m-fire' : 'heroicon-m-check-circle')
                ->chart($this->sparkline5xx($start, $days))
                ->color($server5xx > 0 ? 'danger' : 'success'),

            Stat::make('404 not found', number_format($notFound))
                ->description($notFound > 50 ? 'Possible scanner activity' : 'Broken links')
                ->descriptionIcon($notFound > 50 ? 'heroicon-m-magnifying-glass' : 'heroicon-m-link-slash')
                ->color($notFound > 0 ? 'warning' : 'gray'),

            Stat::make('Error rate', $errorRate.'%')
                ->description($totalHits > 0
                    ? "{$server5xx} of {$totalHits} requests failed"
                    : 'No traffic in this range')
                ->descriptionIcon($errorRate > 1 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-shield-check')
                ->color(match (true) {
                    $errorRate >= 5 => 'danger',
                    $errorRate >= 1 => 'warning',
                    default => 'success',
                }),
        ];
    }

    /** @return array<int, int> */
    private function sparkline5xx(Carbon $start, int $days): array
    {
        // Cap sparkline at 12 buckets — Filament's stat-chart looks cramped
        // beyond that. For longer ranges we aggregate into wider buckets.
        $buckets = min(12, max(1, $days));
        $bucketDays = max(1, (int) ceil($days / $buckets));

        $rows = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $start)
            ->where('status', '>=', 500)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $values = [];
        for ($b = 0; $b < $buckets; $b++) {
            $sum = 0;
            for ($d = 0; $d < $bucketDays; $d++) {
                $date = $start->copy()->addDays($b * $bucketDays + $d);
                if ($date->isFuture()) {
                    break;
                }
                $sum += (int) ($rows[$date->toDateString()] ?? 0);
            }
            $values[] = $sum;
        }

        return $values;
    }
}
