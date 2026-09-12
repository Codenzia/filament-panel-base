<?php

use Codenzia\FilamentPanelBase\NotificationMatrix\Models\NotificationPreference;
use Codenzia\FilamentPanelBase\NotificationMatrix\NotificationTriggers;
use Codenzia\FilamentPanelBase\NotificationMatrix\PreferenceGate;
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
        $table->unique(['user_id', 'user_type', 'trigger_key', 'channel'], 'notification_preferences_unique');
    });

    $this->user = TestUser::create(['email' => 'a@b.com', 'password' => 'x']);

    NotificationTriggers::register('task-off.task.assigned', [
        'label' => 'Task assigned to you',
        'group' => 'Tasks',
    ]);
});

it('returns every candidate channel when nothing is opted out', function (): void {
    $result = PreferenceGate::filterChannels($this->user, 'task-off.task.assigned', ['database', 'mail']);

    expect($result)->toBe(['database', 'mail']);
});

it('drops a channel the user opted out of', function (): void {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'user_type' => $this->user->getMorphClass(),
        'trigger_key' => 'task-off.task.assigned',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    $result = PreferenceGate::filterChannels($this->user, 'task-off.task.assigned', ['database', 'mail']);

    expect($result)->toBe(['database']);
});

it('re-indexes the result array after filtering', function (): void {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'user_type' => $this->user->getMorphClass(),
        'trigger_key' => 'task-off.task.assigned',
        'channel' => 'database',
        'enabled' => false,
    ]);

    $result = PreferenceGate::filterChannels($this->user, 'task-off.task.assigned', ['database', 'mail']);

    expect($result)->toBe([0 => 'mail']);
});

it('passes through unchanged when the module is disabled', function (): void {
    config(['filament-panel-base.notification-matrix.enabled' => false]);

    NotificationPreference::create([
        'user_id' => $this->user->id,
        'user_type' => $this->user->getMorphClass(),
        'trigger_key' => 'task-off.task.assigned',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    $result = PreferenceGate::filterChannels($this->user, 'task-off.task.assigned', ['database', 'mail']);

    expect($result)->toBe(['database', 'mail']);
});
