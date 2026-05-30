<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Console;

use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Codenzia\FilamentPanelBase\Analytics\Models\VisitDaily;
use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Honours AnalyticsSettings::$retain_raw_days and $retain_aggregated_days.
 * Deletes in chunks so a long backlog doesn't lock the table.
 *
 * Scheduled daily at 03:15 by FilamentPanelBaseServiceProvider::bootAnalyticsModule().
 */
class PruneAnalyticsCommand extends Command
{
    protected $signature = 'filament-panel-base:analytics:prune
        {--chunk=2000 : How many rows to delete per query}';

    protected $description = 'Delete raw + aggregated analytics rows past their retention window.';

    public function handle(AnalyticsSettings $settings): int
    {
        $chunk = max(100, (int) $this->option('chunk'));

        $rawCutoff = Carbon::now()->subDays(max(1, $settings->retain_raw_days));
        $aggCutoff = Carbon::now()->subDays(max(1, $settings->retain_aggregated_days));

        $rawDeleted = $this->chunkedDelete(
            fn () => Visit::query()->where('created_at', '<', $rawCutoff),
            $chunk,
        );

        $authDeleted = $this->chunkedDelete(
            fn () => AuthEvent::query()->where('created_at', '<', $aggCutoff),
            $chunk,
        );

        $dailyDeleted = $this->chunkedDelete(
            fn () => VisitDaily::query()->where('date', '<', $aggCutoff->toDateString()),
            $chunk,
        );

        $this->info(sprintf(
            'Pruned: %d visits, %d auth_events, %d visits_daily.',
            $rawDeleted,
            $authDeleted,
            $dailyDeleted,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  callable():\Illuminate\Database\Eloquent\Builder<*>  $factory
     */
    private function chunkedDelete(callable $factory, int $chunk): int
    {
        $total = 0;

        while (true) {
            $deleted = $factory()->limit($chunk)->delete();
            $total += $deleted;

            if ($deleted < $chunk) {
                break;
            }
        }

        return $total;
    }
}
