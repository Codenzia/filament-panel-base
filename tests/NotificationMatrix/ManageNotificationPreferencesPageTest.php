<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\NotificationMatrix\Filament\Pages\ManageNotificationPreferences;
use Codenzia\FilamentPanelBase\NotificationMatrix\Models\NotificationPreference;
use Codenzia\FilamentPanelBase\NotificationMatrix\NotificationTriggers;
use Codenzia\FilamentPanelBase\Tests\Support\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

afterEach(function (): void {
    ManageNotificationPreferences::$authorizeUsing = null;
});

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

    NotificationTriggers::register('task-off.task.assigned', [
        'label' => 'Task assigned to you',
        'group' => 'Tasks',
    ]);
    NotificationTriggers::register('billing.invoice.paid', [
        'label' => 'Invoice paid',
        'group' => 'Billing',
        'channels' => ['mail'],
    ]);

    $this->user = TestUser::create(['email' => 'a@b.com', 'password' => 'x']);
});

it('denies access to an unauthenticated visitor by default', function (): void {
    expect(ManageNotificationPreferences::canAccess())->toBeFalse();
});

it('grants access to any authenticated user by default', function (): void {
    $this->actingAs($this->user);

    expect(ManageNotificationPreferences::canAccess())->toBeTrue();
});

it('honours a host-supplied authorize closure', function (): void {
    $this->actingAs($this->user);
    ManageNotificationPreferences::$authorizeUsing = fn (): bool => false;

    expect(ManageNotificationPreferences::canAccess())->toBeFalse();
});

it('only shows navigation when the module is enabled', function (): void {
    $this->actingAs($this->user);

    config(['filament-panel-base.notification-matrix.enabled' => false]);
    expect(ManageNotificationPreferences::shouldRegisterNavigation())->toBeFalse();

    config(['filament-panel-base.notification-matrix.enabled' => true]);
    expect(ManageNotificationPreferences::shouldRegisterNavigation())->toBeTrue();
});

it('groups registered triggers by their group meta', function (): void {
    $page = new ManageNotificationPreferences;

    $groups = $page->getGroupedTriggers();

    expect(array_keys($groups))->toBe(['Billing', 'Tasks']); // ksort()
    expect($groups['Tasks'])->toHaveKey('task-off.task.assigned');
    expect($groups['Billing'])->toHaveKey('billing.invoice.paid');
});

it('filters triggers by the search term against label and key', function (): void {
    $page = new ManageNotificationPreferences;
    $page->search = 'invoice';

    $groups = $page->getGroupedTriggers();

    expect($groups)->toHaveKey('Billing')
        ->and($groups)->not->toHaveKey('Tasks');
});

it('a trigger reads as enabled for every channel with no preference rows', function (): void {
    $this->actingAs($this->user);
    $page = new ManageNotificationPreferences;

    expect($page->isChannelEnabled('task-off.task.assigned', 'database'))->toBeTrue();
    expect($page->isChannelEnabled('task-off.task.assigned', 'mail'))->toBeTrue();
});

it('toggling a channel off creates an opt-out row that persists', function (): void {
    $this->actingAs($this->user);
    $page = new ManageNotificationPreferences;

    $page->toggleChannel('task-off.task.assigned', 'mail');

    expect($page->isChannelEnabled('task-off.task.assigned', 'mail'))->toBeFalse();
    expect(NotificationPreference::count())->toBe(1);

    $row = NotificationPreference::first();
    expect($row->user_id)->toBe($this->user->id)
        ->and($row->trigger_key)->toBe('task-off.task.assigned')
        ->and($row->channel)->toBe('mail')
        ->and($row->enabled)->toBeFalse();
});

it('toggling a channel back to its default deletes the override row', function (): void {
    $this->actingAs($this->user);
    $page = new ManageNotificationPreferences;

    $page->toggleChannel('task-off.task.assigned', 'mail'); // off
    expect(NotificationPreference::count())->toBe(1);

    $page->toggleChannel('task-off.task.assigned', 'mail'); // back to on (the default)
    expect(NotificationPreference::count())->toBe(0);
    expect($page->isChannelEnabled('task-off.task.assigned', 'mail'))->toBeTrue();
});

it('resetToDefaults removes every preference row for the current user only', function (): void {
    $other = TestUser::create(['email' => 'other@b.com', 'password' => 'x']);

    $this->actingAs($this->user);
    (new ManageNotificationPreferences)->toggleChannel('task-off.task.assigned', 'mail');

    $this->actingAs($other);
    (new ManageNotificationPreferences)->toggleChannel('task-off.task.assigned', 'mail');

    expect(NotificationPreference::count())->toBe(2);

    $this->actingAs($this->user);
    (new ManageNotificationPreferences)->resetToDefaults();

    expect(NotificationPreference::count())->toBe(1);
    expect(NotificationPreference::first()->user_id)->toBe($other->id);
});
