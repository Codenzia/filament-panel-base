<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\NotificationMatrix;

use Codenzia\FilamentPanelBase\NotificationMatrix\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * The seam host apps call from a Notification's `via()` (directly, or through
 * the {@see PreferenceGate} convenience) to decide whether a given user wants
 * a given trigger delivered through a given channel.
 *
 * This package never sends notifications itself — it only answers `allows()`.
 *
 * Resolution order:
 *   1. Module disabled (`filament-panel-base.notification-matrix.enabled` is
 *      false, the default) → always true. Zero behaviour change until a host
 *      opts in.
 *   2. Unregistered trigger key → always true. An unknown trigger can never
 *      be silently blocked by a stale/mistyped key.
 *   3. Storage unavailable — the `notification_preferences` table is absent,
 *      or it exists but belongs to another package (see storageAvailable())
 *      → the trigger's registered default. The matrix is unavailable, not
 *      broken.
 *   4. A stored preference row for (user, trigger, channel) → that row's
 *      `enabled` value wins.
 *   5. No row → the trigger's registered `default_enabled` meta (true unless
 *      the trigger opted into being off by default).
 */
class NotificationPreferences
{
    /** @var array<int, string> Columns this package's own schema guarantees. */
    protected const REQUIRED_COLUMNS = ['user_id', 'user_type', 'trigger_key', 'channel', 'enabled'];

    protected static ?bool $storageAvailable = null;

    public static function allows(Model $user, string $triggerKey, string $channel): bool
    {
        if (! static::moduleEnabled()) {
            return true;
        }

        $meta = NotificationTriggers::get($triggerKey);

        if ($meta === null) {
            return true;
        }

        $defaultEnabled = (bool) ($meta['default_enabled'] ?? true);

        if (! static::storageAvailable()) {
            return $defaultEnabled;
        }

        try {
            $row = NotificationPreference::query()
                ->where('user_id', $user->getKey())
                ->where('user_type', $user->getMorphClass())
                ->where('trigger_key', $triggerKey)
                ->where('channel', $channel)
                ->first();
        } catch (\Throwable) {
            // Table not migrated yet, DB unavailable, etc. — fail open to
            // the registered default rather than blocking delivery.
            return $defaultEnabled;
        }

        return $row !== null ? (bool) $row->enabled : $defaultEnabled;
    }

    /**
     * Whether the `notification_preferences` table exists AND carries this
     * package's own columns. The table name is also taken by
     * `codenzia/notification-module` with a different schema; when that
     * foreign table is what's present the matrix reports itself unavailable
     * — every trigger falls back to its registered default — and says so in
     * the log once per process instead of erroring on every read and write.
     *
     * The probe is cached per process; call flushStorageAvailability() after
     * changing the schema underneath a long-running worker or a test.
     */
    public static function storageAvailable(): bool
    {
        if (static::$storageAvailable !== null) {
            return static::$storageAvailable;
        }

        try {
            $model = new NotificationPreference;
            $schema = $model->getConnection()->getSchemaBuilder();

            $available = $schema->hasTable($model->getTable())
                && $schema->hasColumns($model->getTable(), static::REQUIRED_COLUMNS);
        } catch (\Throwable) {
            return static::$storageAvailable = false;
        }

        if (! $available) {
            Log::warning('[fpb-notification-matrix] The `notification_preferences` table is missing or owned by another package — notification preferences are unavailable and every trigger falls back to its registered default.');
        }

        return static::$storageAvailable = $available;
    }

    /** Forget the cached schema probe (tests, and workers that migrate). */
    public static function flushStorageAvailability(): void
    {
        static::$storageAvailable = null;
    }

    protected static function moduleEnabled(): bool
    {
        return (bool) config('filament-panel-base.notification-matrix.enabled', false);
    }
}
