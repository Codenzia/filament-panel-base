<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * The prune command is the analytics retention path (the visits_daily rollup
 * was removed in PNB-004). It must delete rows past their window, keep recent
 * ones, and be idempotent — a second run with nothing stale deletes nothing.
 */
beforeEach(function (): void {
    Schema::create('visits', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('path', 2048);
        $table->char('method', 8);
        $table->unsignedSmallInteger('status');
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('auth_events', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('type', 40);
        $table->timestamp('created_at')->nullable();
    });

    $settings = $this->settingsStub(AnalyticsSettings::class);
    $settings->retain_raw_days = 7;
    $settings->retain_aggregated_days = 30;
    app()->instance(AnalyticsSettings::class, $settings);
});

afterEach(function (): void {
    Schema::dropIfExists('visits');
    Schema::dropIfExists('auth_events');
});

function seedVisit(Carbon $at): void
{
    Visit::create(['path' => '/x', 'method' => 'GET', 'status' => 200, 'created_at' => $at]);
}

function seedAuthEvent(Carbon $at): void
{
    AuthEvent::create(['type' => AuthEvent::TYPE_LOGIN_SUCCESS, 'created_at' => $at]);
}

it('deletes rows past their retention window and keeps recent ones', function (): void {
    seedVisit(now()->subDays(10));  // older than retain_raw_days (7) → pruned
    seedVisit(now()->subDay());     // within window → kept
    seedAuthEvent(now()->subDays(40)); // older than retain_aggregated_days (30) → pruned
    seedAuthEvent(now()->subDays(5));  // within window → kept

    $this->artisan('filament-panel-base:analytics:prune')->assertSuccessful();

    expect(Visit::query()->count())->toBe(1)
        ->and(AuthEvent::query()->count())->toBe(1);
});

it('is idempotent — a second run deletes nothing more', function (): void {
    seedVisit(now()->subDays(10));
    seedVisit(now()->subDay());

    $this->artisan('filament-panel-base:analytics:prune')->assertSuccessful();
    $afterFirst = Visit::query()->count();

    $this->artisan('filament-panel-base:analytics:prune')->assertSuccessful();

    expect(Visit::query()->count())->toBe($afterFirst)->toBe(1);
});
