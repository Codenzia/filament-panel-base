<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Console;

use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Codenzia\FilamentPanelBase\Analytics\Models\VisitDaily;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates raw `visits` rows into `visits_daily` buckets so widgets can
 * read pre-computed totals. Re-aggregates the last `--days` days (default 2)
 * so late-arriving rows from queue retries are caught.
 *
 * Scheduled hourly by FilamentPanelBaseServiceProvider::bootAnalyticsModule().
 */
class RollupAnalyticsCommand extends Command
{
    protected $signature = 'filament-panel-base:analytics:rollup
        {--days=2 : How many trailing days to recompute}';

    protected $description = 'Aggregate raw visits into visits_daily buckets.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = Carbon::now()->subDays($days)->startOfDay();

        // Re-aggregate by deleting the affected daily rows then re-inserting.
        // Cheaper than upsert-with-coalesce on every group across multiple DB
        // engines, and idempotent.
        $affectedDates = Visit::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d')
            ->distinct()
            ->pluck('d')
            ->all();

        if (empty($affectedDates)) {
            $this->info('No visits found in the rollup window — nothing to do.');

            return self::SUCCESS;
        }

        VisitDaily::whereIn('date', $affectedDates)->delete();

        $rows = Visit::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('
                DATE(created_at) as date,
                panel,
                tenant_id,
                tenant_type,
                country_code,
                COUNT(*) as views,
                COUNT(DISTINCT user_id) as unique_visitors,
                COUNT(DISTINCT session_id) as unique_sessions,
                SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_views
            ')
            ->groupBy('date', 'panel', 'tenant_id', 'tenant_type', 'country_code')
            ->get();

        $now = now();
        $payload = $rows->map(fn ($row): array => [
            'date' => $row->date,
            'panel' => $row->panel,
            'tenant_id' => $row->tenant_id,
            'tenant_type' => $row->tenant_type,
            'country_code' => $row->country_code,
            'views' => (int) $row->views,
            'unique_visitors' => (int) $row->unique_visitors,
            'unique_sessions' => (int) $row->unique_sessions,
            'bot_views' => (int) $row->bot_views,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // Chunk inserts so very busy sites don't blow past the placeholder
        // limit of the underlying driver.
        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table('visits_daily')->insert($chunk);
        }

        $this->info(sprintf(
            'Rolled up %d buckets across %d day(s).',
            count($payload),
            count($affectedDates),
        ));

        return self::SUCCESS;
    }
}
