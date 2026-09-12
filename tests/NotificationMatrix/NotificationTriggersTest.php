<?php

use Codenzia\FilamentPanelBase\NotificationMatrix\NotificationTriggers;

it('starts empty', function (): void {
    expect(NotificationTriggers::all())->toBe([]);
    expect(NotificationTriggers::get('unknown'))->toBeNull();
});

it('registers a trigger with the supplied meta', function (): void {
    NotificationTriggers::register('task-off.task.assigned', [
        'label' => 'Task assigned to you',
        'group' => 'Tasks',
    ]);

    $meta = NotificationTriggers::get('task-off.task.assigned');

    expect($meta)->not->toBeNull()
        ->and($meta['label'])->toBe('Task assigned to you')
        ->and($meta['group'])->toBe('Tasks');
});

it('fills in channels and default_enabled when not supplied', function (): void {
    NotificationTriggers::register('task-off.task.assigned', [
        'label' => 'Task assigned to you',
        'group' => 'Tasks',
    ]);

    $meta = NotificationTriggers::get('task-off.task.assigned');

    expect($meta['channels'])->toBe(['database', 'mail'])
        ->and($meta['default_enabled'])->toBeTrue();
});

it('honours an explicit channels list and default_enabled override', function (): void {
    NotificationTriggers::register('task-off.digest.weekly', [
        'label' => 'Weekly digest',
        'group' => 'Digests',
        'channels' => ['mail'],
        'default_enabled' => false,
    ]);

    $meta = NotificationTriggers::get('task-off.digest.weekly');

    expect($meta['channels'])->toBe(['mail'])
        ->and($meta['default_enabled'])->toBeFalse();
});

it('re-registering the same key overwrites (dedupes) the previous meta', function (): void {
    NotificationTriggers::register('task-off.task.assigned', [
        'label' => 'Old label',
        'group' => 'Tasks',
    ]);
    NotificationTriggers::register('task-off.task.assigned', [
        'label' => 'New label',
        'group' => 'Tasks',
    ]);

    expect(NotificationTriggers::all())->toHaveCount(1);
    expect(NotificationTriggers::get('task-off.task.assigned')['label'])->toBe('New label');
});

it('groups registered triggers by their group meta, preserving registration order', function (): void {
    NotificationTriggers::register('task-off.task.assigned', ['label' => 'Assigned', 'group' => 'Tasks']);
    NotificationTriggers::register('task-off.task.completed', ['label' => 'Completed', 'group' => 'Tasks']);
    NotificationTriggers::register('billing.invoice.paid', ['label' => 'Invoice paid', 'group' => 'Billing']);

    $grouped = NotificationTriggers::grouped();

    expect(array_keys($grouped))->toBe(['Tasks', 'Billing']);
    expect(array_keys($grouped['Tasks']))->toBe(['task-off.task.assigned', 'task-off.task.completed']);
    expect(array_keys($grouped['Billing']))->toBe(['billing.invoice.paid']);
});

it('defaults an unspecified group to "General"', function (): void {
    NotificationTriggers::register('misc.ping', ['label' => 'Ping']);

    expect(NotificationTriggers::get('misc.ping')['group'])->toBe('General');
    expect(NotificationTriggers::grouped())->toHaveKey('General');
});

it('forgets a registered trigger', function (): void {
    NotificationTriggers::register('task-off.task.assigned', ['label' => 'Assigned', 'group' => 'Tasks']);
    NotificationTriggers::forget('task-off.task.assigned');

    expect(NotificationTriggers::get('task-off.task.assigned'))->toBeNull();
    expect(NotificationTriggers::all())->toBe([]);
});

it('is a container singleton — every resolution sees the same registrations', function (): void {
    NotificationTriggers::register('task-off.task.assigned', ['label' => 'Assigned', 'group' => 'Tasks']);

    expect(app(NotificationTriggers::class))->toBe(app(NotificationTriggers::class));
    expect(app(NotificationTriggers::class)->find('task-off.task.assigned'))->not->toBeNull();
});
