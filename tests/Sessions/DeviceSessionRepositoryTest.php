<?php

use Codenzia\FilamentPanelBase\Analytics\Services\UserAgentParser;
use Codenzia\FilamentPanelBase\Sessions\Services\DeviceSessionRepository;
use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->createUsersTable();
    $this->createSessionsTable();

    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');

    $this->repo = new DeviceSessionRepository(new UserAgentParser);

    $this->user = TwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
});

function insertSession(int $userId, string $id, string $ip = '1.1.1.1', string $ua = 'Mozilla', int $minutesAgo = 0): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => $ip,
        'user_agent' => $ua,
        'payload' => '',
        'last_activity' => now()->subMinutes($minutesAgo)->timestamp,
    ]);
}

it('reports the database driver correctly', function (): void {
    expect($this->repo->driverIsDatabase())->toBeTrue();

    config()->set('session.driver', 'file');
    expect($this->repo->driverIsDatabase())->toBeFalse();
});

it('lists every session belonging to a user', function (): void {
    insertSession($this->user->id, 'sess1');
    insertSession($this->user->id, 'sess2');

    $other = TwoFactorUser::create(['email' => 'b@c.com', 'password' => 'x']);
    insertSession($other->id, 'other-sess');

    $sessions = $this->repo->forUser($this->user);

    expect($sessions)->toHaveCount(2);
    expect($sessions->pluck('id')->all())->toEqualCanonicalizing(['sess1', 'sess2']);
});

it('marks the current session via the passed-in id', function (): void {
    insertSession($this->user->id, 'current-sess');
    insertSession($this->user->id, 'other-sess');

    $sessions = $this->repo->forUser($this->user, currentSessionId: 'current-sess');

    $current = $sessions->firstWhere('id', 'current-sess');
    $other = $sessions->firstWhere('id', 'other-sess');

    expect($current->isCurrent)->toBeTrue();
    expect($other->isCurrent)->toBeFalse();
});

it('parses user-agent into browser + platform + device type', function (): void {
    insertSession($this->user->id, 'sess1', '1.1.1.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit Chrome/120.0.0.0 Safari');

    $session = $this->repo->forUser($this->user)->first();

    expect($session->browser)->toContain('Chrome');
    expect($session->platform)->toContain('macOS');
    expect($session->deviceType)->toBe('desktop');
});

it('orders sessions by recency', function (): void {
    insertSession($this->user->id, 'oldest', minutesAgo: 60);
    insertSession($this->user->id, 'newest', minutesAgo: 1);
    insertSession($this->user->id, 'middle', minutesAgo: 30);

    $ids = $this->repo->forUser($this->user)->pluck('id')->all();

    expect($ids)->toBe(['newest', 'middle', 'oldest']);
});

it('revokes one session', function (): void {
    insertSession($this->user->id, 'sess1');
    insertSession($this->user->id, 'sess2');

    $removed = $this->repo->revoke($this->user, 'sess1');

    expect($removed)->toBeTrue();
    expect(DB::table('sessions')->count())->toBe(1);
});

it('refuses to revoke a session that does not belong to the user', function (): void {
    $other = TwoFactorUser::create(['email' => 'b@c.com', 'password' => 'x']);
    insertSession($other->id, 'not-mine');

    $removed = $this->repo->revoke($this->user, 'not-mine');

    expect($removed)->toBeFalse();
    expect(DB::table('sessions')->count())->toBe(1);
});

it('revokes everything except the current session', function (): void {
    insertSession($this->user->id, 'keep');
    insertSession($this->user->id, 'drop1');
    insertSession($this->user->id, 'drop2');

    $count = $this->repo->revokeAllExcept($this->user, keepSessionId: 'keep');

    expect($count)->toBe(2);
    expect(DB::table('sessions')->pluck('id')->all())->toBe(['keep']);
});

it('throws when the session driver is not database', function (): void {
    config()->set('session.driver', 'file');

    expect(fn () => $this->repo->forUser($this->user))
        ->toThrow(RuntimeException::class);
});

/*
|--------------------------------------------------------------------------
| Session connection and lifetime (PB-06)
|--------------------------------------------------------------------------
*/

it('hides sessions that are already past the configured lifetime (PB-06)', function (): void {
    config()->set('session.lifetime', 120);

    insertSession($this->user->id, 'live-sess', minutesAgo: 10);
    insertSession($this->user->id, 'dead-sess', minutesAgo: 500);

    $sessions = $this->repo->forUser($this->user);

    expect($sessions->pluck('id')->all())->toBe(['live-sess']);
});

it('counts only genuinely active sessions when revoking the rest (PB-06)', function (): void {
    config()->set('session.lifetime', 120);

    insertSession($this->user->id, 'current-sess');
    insertSession($this->user->id, 'other-live');
    insertSession($this->user->id, 'other-dead', minutesAgo: 500);

    $revoked = $this->repo->revokeAllExcept($this->user, 'current-sess');

    expect($revoked)->toBe(1)
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['current-sess']);
});

it('reads and revokes on the configured session connection (PB-06)', function (): void {
    // A second, distinct database standing in for a dedicated session store.
    config()->set('database.connections.sessions', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('session.connection', 'sessions');

    Schema::connection('sessions')->create('sessions', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });

    DB::connection('sessions')->table('sessions')->insert([
        'id' => 'remote-sess',
        'user_id' => $this->user->id,
        'ip_address' => '1.1.1.1',
        'user_agent' => 'Mozilla',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    // A same-named row on the DEFAULT connection that must be left alone.
    insertSession($this->user->id, 'default-sess');

    $repo = new DeviceSessionRepository(new UserAgentParser);

    expect($repo->forUser($this->user)->pluck('id')->all())->toBe(['remote-sess']);

    expect($repo->revoke($this->user, 'remote-sess'))->toBeTrue()
        ->and(DB::connection('sessions')->table('sessions')->count())->toBe(0)
        ->and(DB::table('sessions')->count())->toBe(1);

    Schema::connection('sessions')->drop('sessions');
});
