<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\AuthFunnelWidget;
use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * The funnel used to divide distinct logins by distinct registrations inside
 * the window, which is not a cohort: every user who registered months ago and
 * logged in today inflated the numerator, and panels routinely reported
 * "Conversion 200%" or "450%". Each step is now counted against the window's
 * registration cohort, so no step can exceed "Registered".
 */
beforeEach(function (): void {
    (require __DIR__.'/../../database/migrations/analytics/create_auth_events_table.php')->up();
});

afterEach(function (): void {
    Schema::dropIfExists('auth_events');
});

function funnelEvent(string $type, int $userId, ?Carbon $at = null): void
{
    AuthEvent::create([
        'type' => $type,
        'user_id' => $userId,
        'created_at' => $at ?? now()->subDay(),
    ]);
}

/**
 * @return array<string, array{value: string, description: string}>
 */
function funnelStats(string $range = '7d'): array
{
    $widget = new AuthFunnelWidget;
    $widget->pageFilters = ['range' => $range];

    $stats = (fn (): array => $this->getStats())->call($widget);

    $byLabel = [];

    foreach ($stats as $stat) {
        $byLabel[(string) $stat->getLabel()] = [
            'value' => (string) $stat->getValue(),
            'description' => (string) $stat->getDescription(),
        ];
    }

    return $byLabel;
}

it('never reports more than 100% conversion when logins outnumber registrations', function (): void {
    // Two people registered this week.
    funnelEvent(AuthEvent::TYPE_REGISTER, 1);
    funnelEvent(AuthEvent::TYPE_REGISTER, 2);

    // Nine distinct people logged in — seven of them registered long ago.
    foreach (range(1, 9) as $userId) {
        funnelEvent(AuthEvent::TYPE_LOGIN_SUCCESS, $userId);
        funnelEvent(AuthEvent::TYPE_LOGIN_SUCCESS, $userId); // repeat logins must not double-count
    }

    $stats = funnelStats();

    // Pre-fix this read "9" and "450%".
    expect($stats['Registered']['value'])->toBe('2')
        ->and($stats['First login']['value'])->toBe('2')
        ->and($stats['Conversion']['value'])->toBe('100%')
        ->and($stats['Conversion']['description'])->toBe('2 of 2 registrants logged in');

    expect((int) rtrim($stats['Conversion']['value'], '%'))->toBeLessThanOrEqual(100);
});

it('counts only the registrants who came back to log in', function (): void {
    foreach (range(1, 4) as $userId) {
        funnelEvent(AuthEvent::TYPE_REGISTER, $userId);
    }

    funnelEvent(AuthEvent::TYPE_LOGIN_SUCCESS, 1);
    funnelEvent(AuthEvent::TYPE_LOGIN_SUCCESS, 2);
    funnelEvent(AuthEvent::TYPE_LOGIN_SUCCESS, 77); // never registered in this window

    $stats = funnelStats();

    expect($stats['Registered']['value'])->toBe('4')
        ->and($stats['First login']['value'])->toBe('2')
        ->and($stats['Conversion']['value'])->toBe('50%')
        ->and($stats['Conversion']['description'])->toBe('2 of 4 registrants logged in');
});

it('ignores registrations and logins from outside the window', function (): void {
    funnelEvent(AuthEvent::TYPE_REGISTER, 1, now()->subDays(30));
    funnelEvent(AuthEvent::TYPE_LOGIN_SUCCESS, 1, now()->subDays(30));
    funnelEvent(AuthEvent::TYPE_REGISTER, 2);

    $stats = funnelStats();

    expect($stats['Registered']['value'])->toBe('1')
        ->and($stats['First login']['value'])->toBe('0')
        ->and($stats['Conversion']['value'])->toBe('0%');
});

it('keeps the verified step inside the cohort so its ratio stays under 100%', function (): void {
    funnelEvent(AuthEvent::TYPE_REGISTER, 1);

    funnelEvent(AuthEvent::TYPE_OTP_VERIFIED, 1);
    foreach (range(2, 6) as $userId) {
        funnelEvent(AuthEvent::TYPE_OTP_VERIFIED, $userId); // verified, but registered earlier
    }

    $stats = funnelStats();

    // Pre-fix this read "6" and "600% of registrants".
    expect($stats['Verified']['value'])->toBe('1')
        ->and($stats['Verified']['description'])->toBe('100% of registrants');
});

it('shows the verified step as n/a when the flow has no OTP events', function (): void {
    funnelEvent(AuthEvent::TYPE_REGISTER, 1);
    funnelEvent(AuthEvent::TYPE_LOGIN_SUCCESS, 1);

    $stats = funnelStats();

    expect($stats['Verified']['value'])->toBe('n/a')
        ->and($stats['Verified']['description'])->toBe('No OTP step in this flow');
});
