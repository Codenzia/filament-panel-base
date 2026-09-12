<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets;

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\OnlyOnAnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns\ReadsAnalyticsFilters;
use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Conversion funnel for the selected window:
 *
 *   register → otp.verified → login.success
 *
 * The funnel is a **cohort**: "Registered" counts the distinct users who
 * registered inside the window, and every later step counts only those same
 * users. A user who verified and then logged in 50 times still counts once,
 * and a user who registered last year but logged in today is not counted at
 * all — they are not part of this window's cohort. That makes each step a
 * subset of the one above it, so no ratio can exceed 100%.
 *
 * The OTP-verified step is optional in flows that don't require verification —
 * if no `otp.verified` events exist in the window, it's shown as "n/a" and the
 * funnel collapses to register → first login.
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

        $registrations = $this->scopeAnalyticsTenant(
            AuthEvent::query()->ofType(AuthEvent::TYPE_REGISTER)
        )
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id');

        $registered = (clone $registrations)->distinct('user_id')->count('user_id');

        // Whether the flow has an OTP step at all is a property of the panel,
        // not of the cohort — so it is asked of every event in the window.
        $otpStepInUse = $this->scopeAnalyticsTenant(
            AuthEvent::query()->ofType(AuthEvent::TYPE_OTP_VERIFIED)
        )
            ->where('created_at', '>=', $since)
            ->exists();

        $verified = $this->cohortCount(AuthEvent::TYPE_OTP_VERIFIED, $since, $registrations);
        $loggedIn = $this->cohortCount(AuthEvent::TYPE_LOGIN_SUCCESS, $since, $registrations);

        $conversion = $registered > 0
            ? (int) round(($loggedIn / $registered) * 100)
            : 0;

        return [
            Stat::make('Registered', number_format($registered))
                ->description('New users this period')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary'),

            Stat::make('Verified', $otpStepInUse ? number_format($verified) : 'n/a')
                ->description($otpStepInUse ? $this->ratioDescription($verified, $registered) : 'No OTP step in this flow')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($otpStepInUse ? 'info' : 'gray'),

            Stat::make('First login', number_format($loggedIn))
                ->description('Registrants who logged in')
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

    /**
     * Distinct users from this window's registration cohort who also produced
     * an event of `$type` inside the window.
     *
     * The user ids are constrained by a subquery against the same registration
     * query that produced the "Registered" figure, so the result is always
     * ≤ that figure — the fix for funnels that used to report 200%+ by
     * dividing every logged-in user by only the newly registered ones.
     *
     * @param  Builder<AuthEvent>|QueryBuilder  $registrations
     */
    private function cohortCount(string $type, Carbon $since, Builder|QueryBuilder $registrations): int
    {
        return $this->scopeAnalyticsTenant(
            AuthEvent::query()->ofType($type)
        )
            ->where('created_at', '>=', $since)
            ->whereIn('user_id', (clone $registrations)->select('user_id'))
            ->distinct('user_id')
            ->count('user_id');
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
