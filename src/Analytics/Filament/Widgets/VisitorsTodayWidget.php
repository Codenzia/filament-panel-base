<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets;

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\OnlyOnAnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\ReadsAnalyticsFilters;
use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class VisitorsTodayWidget extends StatsOverviewWidget
{
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    protected ?string $heading = 'Today';

    protected function getStats(): array
    {
        // Fresh install — analytics migrations haven't run yet. Render a
        // friendly hint instead of letting the underlying SQL blow up.
        if (! Schema::hasTable('visits') || ! Schema::hasTable('auth_events')) {
            return [
                Stat::make('Analytics', 'Not ready')
                    ->description('Run php artisan migrate to create the analytics tables.')
                    ->descriptionIcon('heroicon-m-wrench-screwdriver')
                    ->color('gray'),
            ];
        }

        $todayStart = Carbon::today();
        $yesterdayStart = Carbon::yesterday();

        $pageViewsToday = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $todayStart)
            ->count();

        $pageViewsYesterday = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->whereBetween('created_at', [$yesterdayStart, $todayStart])
            ->count();

        $uniqueSessionsToday = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', $todayStart)
            ->distinct('session_id')
            ->count('session_id');

        $activeNow = $this->scopeAnalyticsTenant(Visit::query()->humans())
            ->where('created_at', '>=', Carbon::now()->subMinutes(5))
            ->distinct('session_id')
            ->count('session_id');

        $failedLoginsToday = $this->scopeAnalyticsTenant(
            AuthEvent::query()->ofType(AuthEvent::TYPE_LOGIN_FAILED)
        )
            ->where('created_at', '>=', $todayStart)
            ->count();

        return [
            Stat::make('Active now', number_format($activeNow))
                ->description('Sessions active in the last 5 minutes')
                ->descriptionIcon('heroicon-m-bolt')
                ->color($activeNow > 0 ? 'success' : 'gray'),

            Stat::make('Page views', number_format($pageViewsToday))
                ->description($this->describeChange($pageViewsToday, $pageViewsYesterday))
                ->descriptionIcon($this->changeIcon($pageViewsToday, $pageViewsYesterday))
                ->color($this->changeColor($pageViewsToday, $pageViewsYesterday)),

            Stat::make('Unique sessions', number_format($uniqueSessionsToday))
                ->description('Distinct browser sessions today')
                ->color('primary'),

            Stat::make('Failed logins', number_format($failedLoginsToday))
                ->description($failedLoginsToday > 10 ? 'Elevated' : 'Normal')
                ->descriptionIcon($failedLoginsToday > 10 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-shield-check')
                ->color($failedLoginsToday > 10 ? 'danger' : 'success'),
        ];
    }

    public function getPollingInterval(): ?string
    {
        return '30s';
    }

    private function describeChange(int $today, int $yesterday): string
    {
        if ($yesterday === 0) {
            return $today === 0 ? 'No data yesterday' : 'New activity';
        }

        $pct = (int) round((($today - $yesterday) / $yesterday) * 100);

        return ($pct >= 0 ? '+' : '').$pct.'% vs yesterday';
    }

    private function changeIcon(int $today, int $yesterday): string
    {
        return $today >= $yesterday ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
    }

    private function changeColor(int $today, int $yesterday): string
    {
        return $today >= $yesterday ? 'success' : 'warning';
    }
}
