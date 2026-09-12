<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sessions\Services;

use Codenzia\FilamentPanelBase\Analytics\Services\UserAgentParser;
use Codenzia\FilamentPanelBase\Sessions\Data\DeviceSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Queries Laravel's `sessions` table — on `session.connection`, not the
 * default one — for active sessions belonging to a specific authenticated
 * user. Only works when SESSION_DRIVER=database;
 * other drivers throw a clear RuntimeException so callers can render a
 * "configure database sessions to see this" notice instead of crashing.
 */
class DeviceSessionRepository
{
    /** Resolved once per instance: schema discovery is not free per row. */
    private ?string $userIdColumn = null;

    public function __construct(private UserAgentParser $userAgentParser) {}

    public function driverIsDatabase(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return Collection<int, DeviceSession>
     */
    public function forUser(Authenticatable $user, ?string $currentSessionId = null): Collection
    {
        $this->guardDriver();

        $userIdColumn = $this->userIdColumn();

        // Garbage collection is probabilistic, so the table routinely holds
        // rows the session guard would already refuse. Presenting those as
        // active devices invites a user to "revoke" something already dead.
        $rows = $this->table()
            ->where($userIdColumn, $user->getAuthIdentifier())
            ->where('last_activity', '>=', $this->activeSince())
            ->orderByDesc('last_activity')
            ->get();

        return $rows->map(fn ($row): DeviceSession => $this->hydrate($row, $userIdColumn, $currentSessionId));
    }

    public function revoke(Authenticatable $user, string $sessionId): bool
    {
        $this->guardDriver();

        $deleted = $this->table()
            ->where('id', $sessionId)
            ->where($this->userIdColumn(), $user->getAuthIdentifier())
            ->delete();

        return $deleted > 0;
    }

    /**
     * Delete every session for this user EXCEPT the one belonging to the
     * current request. Used by the "Sign out everywhere else" action.
     *
     * Expired rows are swept along with the rest, but the returned count is
     * the number of sessions that were actually still usable — that is the
     * number the user is being told about.
     */
    public function revokeAllExcept(Authenticatable $user, string $keepSessionId): int
    {
        $this->guardDriver();

        $userIdColumn = $this->userIdColumn();
        $identifier = $user->getAuthIdentifier();

        $active = $this->table()
            ->where($userIdColumn, $identifier)
            ->where('id', '!=', $keepSessionId)
            ->where('last_activity', '>=', $this->activeSince())
            ->count();

        $this->table()
            ->where($userIdColumn, $identifier)
            ->where('id', '!=', $keepSessionId)
            ->delete();

        return $active;
    }

    /**
     * Sessions live on `session.connection`, which is frequently not the
     * default one. Querying the default connection either explodes or — worse
     * — quietly reports no devices while the real sessions stay signed in.
     */
    private function connection(): ?string
    {
        $connection = config('session.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    private function table(): Builder
    {
        return DB::connection($this->connection())
            ->table((string) config('session.table', 'sessions'));
    }

    /**
     * Unix timestamp before which a session row is past the configured
     * lifetime and no longer a device anyone is signed in on.
     */
    private function activeSince(): int
    {
        $lifetime = max(1, (int) config('session.lifetime', 120));

        return now()->subMinutes($lifetime)->getTimestamp();
    }

    private function hydrate(object $row, string $userIdColumn, ?string $currentSessionId): DeviceSession
    {
        $ua = isset($row->user_agent) ? (string) $row->user_agent : null;
        $parsed = $this->userAgentParser->parse($ua);

        return new DeviceSession(
            id: (string) $row->id,
            userId: isset($row->{$userIdColumn}) ? (int) $row->{$userIdColumn} : null,
            ipAddress: (string) ($row->ip_address ?? ''),
            userAgent: $ua,
            browser: $parsed['browser'] ?? null,
            platform: $parsed['platform'] ?? null,
            deviceType: $parsed['device'] ?? 'desktop',
            lastActivity: Carbon::createFromTimestamp((int) ($row->last_activity ?? 0)),
            isCurrent: $currentSessionId !== null && $row->id === $currentSessionId,
        );
    }

    private function userIdColumn(): string
    {
        if ($this->userIdColumn !== null) {
            return $this->userIdColumn;
        }

        $table = (string) config('session.table', 'sessions');
        $schema = Schema::connection($this->connection());

        // Laravel's default migration calls it `user_id`. Older codebases
        // occasionally use `userId` — fall back if needed.
        if ($schema->hasColumn($table, 'user_id')) {
            return $this->userIdColumn = 'user_id';
        }

        if ($schema->hasColumn($table, 'userId')) {
            return $this->userIdColumn = 'userId';
        }

        return $this->userIdColumn = 'user_id';
    }

    private function guardDriver(): void
    {
        if (! $this->driverIsDatabase()) {
            throw new RuntimeException(
                'Session management requires the database session driver. '
                .'Set SESSION_DRIVER=database in your .env and run the sessions table migration.'
            );
        }
    }
}
