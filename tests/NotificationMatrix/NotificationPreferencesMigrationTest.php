<?php

use Codenzia\FilamentPanelBase\NotificationMatrix\NotificationPreferences;
use Codenzia\FilamentPanelBase\NotificationMatrix\NotificationTriggers;
use Codenzia\FilamentPanelBase\Tests\Support\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;

/**
 * Hosts on the Codenzia platform stack already own a `notification_preferences`
 * table through codenzia/notification-module, with a schema of its own. The
 * package migration must stand aside instead of aborting `migrate`.
 */
$foreignTable = function (): void {
    Schema::create('notification_preferences', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('notification_key', 191);
        $table->json('channels')->nullable();
        $table->timestamps();
    });
};

$migrate = fn (): PendingCommand => test()->artisan('migrate', [
    '--path' => realpath(__DIR__.'/../../database/migrations/notification_matrix'),
    '--realpath' => true,
]);

it('runs the package migration without error when a foreign table already owns the name', function () use ($foreignTable, $migrate): void {
    $foreignTable();

    $migrate()->assertSuccessful();

    expect(Schema::hasColumn('notification_preferences', 'notification_key'))->toBeTrue()
        ->and(Schema::hasColumn('notification_preferences', 'trigger_key'))->toBeFalse();
});

it('does not drop a foreign table on rollback', function () use ($foreignTable, $migrate): void {
    $foreignTable();
    $migrate()->assertSuccessful();

    test()->artisan('migrate:rollback', [
        '--path' => realpath(__DIR__.'/../../database/migrations/notification_matrix'),
        '--realpath' => true,
    ])->assertSuccessful();

    expect(Schema::hasTable('notification_preferences'))->toBeTrue()
        ->and(Schema::hasColumn('notification_preferences', 'notification_key'))->toBeTrue();
});

it('creates its own table when nothing else owns the name', function () use ($migrate): void {
    $migrate()->assertSuccessful();

    expect(Schema::hasColumn('notification_preferences', 'trigger_key'))->toBeTrue();
    expect(NotificationPreferences::storageAvailable())->toBeTrue();
});

it('reports storage unavailable and falls back to trigger defaults on a foreign schema', function () use ($foreignTable, $migrate): void {
    config(['filament-panel-base.notification-matrix.enabled' => true]);
    $foreignTable();
    $migrate()->assertSuccessful();

    $this->createUsersTable();
    $user = TestUser::create(['email' => 'foreign@example.com', 'password' => 'x']);

    NotificationTriggers::register('task-off.task.assigned', ['label' => 'Task assigned', 'group' => 'Tasks']);
    NotificationTriggers::register('task-off.digest.weekly', [
        'label' => 'Weekly digest',
        'group' => 'Tasks',
        'default_enabled' => false,
    ]);

    expect(NotificationPreferences::storageAvailable())->toBeFalse()
        ->and(NotificationPreferences::allows($user, 'task-off.task.assigned', 'mail'))->toBeTrue()
        ->and(NotificationPreferences::allows($user, 'task-off.digest.weekly', 'mail'))->toBeFalse();
});
