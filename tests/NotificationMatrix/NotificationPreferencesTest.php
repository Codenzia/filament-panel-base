<?php

use Codenzia\FilamentPanelBase\NotificationMatrix\Models\NotificationPreference;
use Codenzia\FilamentPanelBase\NotificationMatrix\NotificationPreferences;
use Codenzia\FilamentPanelBase\NotificationMatrix\NotificationTriggers;
use Codenzia\FilamentPanelBase\Tests\Support\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config(['filament-panel-base.notification-matrix.enabled' => true]);

    $this->createUsersTable();

    Schema::create('notification_preferences', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('user_type', 191);
        $table->string('trigger_key', 191);
        $table->string('channel', 40);
        $table->boolean('enabled')->default(true);
        $table->timestamps();
        $table->index(['user_id', 'user_type']);
        $table->unique(['user_id', 'user_type', 'trigger_key', 'channel'], 'notification_preferences_unique');
    });

    $this->user = TestUser::create(['email' => 'a@b.com', 'password' => 'x']);

    NotificationTriggers::register('task-off.task.assigned', [
        'label' => 'Task assigned to you',
        'group' => 'Tasks',
    ]);
});

it('always allows when the module is disabled, even with an opt-out row', function (): void {
    config(['filament-panel-base.notification-matrix.enabled' => false]);

    NotificationPreference::create([
        'user_id' => $this->user->id,
        'user_type' => $this->user->getMorphClass(),
        'trigger_key' => 'task-off.task.assigned',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    expect(NotificationPreferences::allows($this->user, 'task-off.task.assigned', 'mail'))->toBeTrue();
});

it('always allows an unregistered trigger key', function (): void {
    expect(NotificationPreferences::allows($this->user, 'no.such.trigger', 'mail'))->toBeTrue();
});

it('defaults to allowed when no preference row exists', function (): void {
    expect(NotificationPreferences::allows($this->user, 'task-off.task.assigned', 'mail'))->toBeTrue();
    expect(NotificationPreferences::allows($this->user, 'task-off.task.assigned', 'database'))->toBeTrue();
});

it('honours a trigger registered with default_enabled false when no row exists', function (): void {
    NotificationTriggers::register('task-off.digest.weekly', [
        'label' => 'Weekly digest',
        'group' => 'Digests',
        'default_enabled' => false,
    ]);

    expect(NotificationPreferences::allows($this->user, 'task-off.digest.weekly', 'mail'))->toBeFalse();
});

it('an opt-out row wins over the default-on trigger', function (): void {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'user_type' => $this->user->getMorphClass(),
        'trigger_key' => 'task-off.task.assigned',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    expect(NotificationPreferences::allows($this->user, 'task-off.task.assigned', 'mail'))->toBeFalse();
    // The other channel is untouched — still allowed.
    expect(NotificationPreferences::allows($this->user, 'task-off.task.assigned', 'database'))->toBeTrue();
});

it('an opt-in row wins over a default-off trigger', function (): void {
    NotificationTriggers::register('task-off.digest.weekly', [
        'label' => 'Weekly digest',
        'group' => 'Digests',
        'default_enabled' => false,
    ]);

    NotificationPreference::create([
        'user_id' => $this->user->id,
        'user_type' => $this->user->getMorphClass(),
        'trigger_key' => 'task-off.digest.weekly',
        'channel' => 'mail',
        'enabled' => true,
    ]);

    expect(NotificationPreferences::allows($this->user, 'task-off.digest.weekly', 'mail'))->toBeTrue();
});

it('only matches rows for the given user', function (): void {
    $other = TestUser::create(['email' => 'other@b.com', 'password' => 'x']);

    NotificationPreference::create([
        'user_id' => $other->id,
        'user_type' => $other->getMorphClass(),
        'trigger_key' => 'task-off.task.assigned',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    expect(NotificationPreferences::allows($this->user, 'task-off.task.assigned', 'mail'))->toBeTrue();
    expect(NotificationPreferences::allows($other, 'task-off.task.assigned', 'mail'))->toBeFalse();
});

it('fails open to the registered default when the table is unavailable', function (): void {
    Schema::drop('notification_preferences');

    expect(NotificationPreferences::allows($this->user, 'task-off.task.assigned', 'mail'))->toBeTrue();
});
