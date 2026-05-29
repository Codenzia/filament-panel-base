<?php

use Codenzia\FilamentPanelBase\Sessions\Data\DeviceSession;
use Illuminate\Support\Carbon;

it('builds a human-readable label from browser and platform', function (): void {
    $session = new DeviceSession(
        id: 'abc',
        userId: 1,
        ipAddress: '1.1.1.1',
        userAgent: 'Mozilla/...',
        browser: 'Chrome 120',
        platform: 'macOS 14.2',
        deviceType: 'desktop',
        lastActivity: Carbon::now(),
        isCurrent: true,
    );

    expect($session->label())->toBe('Chrome 120 · macOS 14.2');
});

it('falls back to Unknown when browser or platform is missing', function (): void {
    $session = new DeviceSession(
        id: 'abc',
        userId: 1,
        ipAddress: '1.1.1.1',
        userAgent: null,
        browser: null,
        platform: null,
        deviceType: 'desktop',
        lastActivity: Carbon::now(),
        isCurrent: false,
    );

    expect($session->label())->toBe('Unknown browser · Unknown OS');
});
