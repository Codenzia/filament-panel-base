<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sessions\Services;

use Codenzia\FilamentPanelBase\Analytics\Services\UserAgentParser;
use Codenzia\FilamentPanelBase\Sessions\Data\DeviceSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Queries Laravel's `sessions` table for active sessions belonging to a
 * specific authenticated user. Only works when SESSION_DRIVER=database;
 * other drivers throw a clear RuntimeException so callers can render a
 * "configure database sessions to see this" notice instead of crashing.
 */
class DeviceSessionRepository
{
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

        $table = (string) config('session.table', 'sessions');
        $userIdColumn = $this->detectUserIdColumn($table);

        $rows = DB::table($table)
            ->where($userIdColumn, $user->getAuthIdentifier())
            ->orderByDesc('last_activity')
            ->get();

        return $rows->map(fn ($row): DeviceSession => $this->hydrate($row, $userIdColumn, $currentSessionId));
    }

    public function revoke(Authenticatable $user, string $sessionId): bool
    {
        $this->guardDriver();

        $table = (string) config('session.table', 'sessions');
        $userIdColumn = $this->detectUserIdColumn($table);

        $deleted = DB::table($table)
            ->where('id', $sessionId)
            ->where($userIdColumn, $user->getAuthIdentifier())
            ->delete();

        return $deleted > 0;
    }

    /**
     * Delete every session for this user EXCEPT the one belonging to the
     * current request. Used by the "Sign out everywhere else" action.
     */
    public function revokeAllExcept(Authenticatable $user, string $keepSessionId): int
    {
        $this->guardDriver();

        $table = (string) config('session.table', 'sessions');
        $userIdColumn = $this->detectUserIdColumn($table);

        return DB::table($table)
            ->where($userIdColumn, $user->getAuthIdentifier())
            ->where('id', '!=', $keepSessionId)
            ->delete();
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

    private function detectUserIdColumn(string $table): string
    {
        // Laravel's default migration calls it `user_id`. Older codebases
        // occasionally use `userId` — fall back if needed.
        if (Schema::hasColumn($table, 'user_id')) {
            return 'user_id';
        }

        if (Schema::hasColumn($table, 'userId')) {
            return 'userId';
        }

        return 'user_id';
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
