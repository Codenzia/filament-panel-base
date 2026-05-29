<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets;

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\OnlyOnAnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\ReadsAnalyticsFilters;
use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

/**
 * Conversion funnel for the last 30 days:
 *
 *   register → otp.verified → login.success
 *
 * Each step counts DISTINCT user_id within the window, so a user who
 * verified and then logged in 50 times still counts as one converted user.
 * The OTP-verified step is optional in flows that don't require verification —
 * if no `otp.verified` events exist, it's shown as "n/a" and the funnel
 * collapses to register → first login.
 */
class AuthFunnelWidget extends StatsOverviewWidget
{
    use OnlyOnAnalyticsPage;
    use ReadsAnalyticsFilters;

    public function getHeading(): ?string
    {
        return 'Signup funnel — '.$this->getRangeLabel();
    }

    protected function getStats(): array
    {
        if (! Schema::hasTable('auth_events')) {
            return [
                Stat::make('Funnel', 'Not ready')
                    ->description('Run php artisan migrate.')
                    ->color('gray'),
            ];
        }

        $since = $this->getRangeStart();

        $registered = $this->scopeAnalyticsTenant(
            AuthEvent::query()->ofType(AuthEvent::TYPE_REGISTER)
        )
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $verified = $this->scopeAnalyticsTenant(
            AuthEvent::query()->ofType(AuthEvent::TYPE_OTP_VERIFIED)
        )
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $loggedIn = $this->scopeAnalyticsTenant(
            AuthEvent::query()->ofType(AuthEvent::TYPE_LOGIN_SUCCESS)
        )
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $conversion = $registered > 0
            ? (int) round(($loggedIn / $registered) * 100)
            : 0;

        return [
            Stat::make('Registered', number_format($registered))
                ->description('New users this period')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary'),

            Stat::make('Verified', $verified > 0 ? number_format($verified) : 'n/a')
                ->description($verified > 0 ? $this->ratioDescription($verified, $registered) : 'No OTP step in this flow')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($verified > 0 ? 'info' : 'gray'),

            Stat::make('First login', number_format($loggedIn))
                ->description('Distinct users who logged in')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('success'),

            Stat::make('Conversion', $conversion.'%')
                ->description($registered === 0
                    ? 'No signups yet'
                    : "{$loggedIn} of {$registered} registrants logged in")
                ->descriptionIcon($conversion >= 50 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($this->conversionColor($conversion)),
        ];
    }

    private function ratioDescription(int $part, int $whole): string
    {
        if ($whole === 0) {
            return 'No baseline';
        }

        $pct = (int) round(($part / $whole) * 100);

        return "{$pct}% of registrants";
    }

    private function conversionColor(int $pct): string
    {
        return match (true) {
            $pct >= 50 => 'success',
            $pct >= 25 => 'warning',
            default => 'danger',
        };
    }
}
