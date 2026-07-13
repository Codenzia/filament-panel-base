<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Codenzia\FilamentPanelBase\Analytics\Services\BotDetector;
use Codenzia\FilamentPanelBase\Analytics\Services\IpAnonymizer;
use Codenzia\FilamentPanelBase\Analytics\Services\UserAgentParser;
use Codenzia\FilamentPanelBase\Analytics\Services\VisitWriter;
use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * VisitWriter builds a visit row from the request/response and either queues
 * it or writes it synchronously. Pins the field mapping (incl. bot flag and
 * IP anonymisation), the enabled/track_visits gates, and the sync insert.
 */
function visitWriter(bool $enabled = true, bool $trackVisits = true): VisitWriter
{
    /** @var AnalyticsSettings $settings */
    $settings = test()->settingsStub(AnalyticsSettings::class);
    $settings->enabled = $enabled;
    $settings->track_visits = $trackVisits;
    $settings->ip_anonymization = 'truncate';
    $settings->write_queue = null; // synchronous

    return new VisitWriter(
        $settings,
        new BotDetector,
        new IpAnonymizer($settings),
        new UserAgentParser,
    );
}

function chromeRequest(?string $ua = null): Request
{
    return Request::create('/dashboard', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $ua ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            .'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'REMOTE_ADDR' => '203.0.113.7',
    ]);
}

it('build() maps request/response fields and anonymises the IP', function (): void {
    $data = visitWriter()->build(chromeRequest(), new Response('', 201), null);

    expect($data->path)->toBe('dashboard')
        ->and($data->method)->toBe('GET')
        ->and($data->status)->toBe(201)
        ->and($data->isBot)->toBeFalse()
        ->and($data->browser)->toBe('Chrome 125')
        ->and($data->deviceType)->toBe('desktop')
        // IP is hashed, never stored raw.
        ->and($data->ipHash)->toBeString()
        ->and(strlen($data->ipHash))->toBe(64)
        ->and($data->ipHash)->not->toContain('203.0.113');
});

it('build() flags a bot user agent', function (): void {
    $data = visitWriter()->build(
        chromeRequest('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'),
        new Response('', 200),
    );

    expect($data->isBot)->toBeTrue();
});

it('build() records request duration when a start time is given', function (): void {
    $data = visitWriter()->build(chromeRequest(), new Response('', 200), microtime(true) - 0.05);

    expect($data->durationMs)->toBeInt()->toBeGreaterThanOrEqual(40);
});

it('record() writes nothing when analytics is disabled', function (): void {
    createVisitsTable();

    visitWriter(enabled: false)->record(chromeRequest(), new Response('', 200));

    expect(Visit::query()->count())->toBe(0);
});

it('record() writes nothing when track_visits is off', function (): void {
    createVisitsTable();

    visitWriter(trackVisits: false)->record(chromeRequest(), new Response('', 200));

    expect(Visit::query()->count())->toBe(0);
});

it('record() synchronously inserts a visit row when enabled and no queue is set', function (): void {
    createVisitsTable();

    visitWriter()->record(chromeRequest(), new Response('', 200));

    $row = Visit::query()->sole();

    expect($row->path)->toBe('dashboard')
        ->and($row->status)->toBe(200)
        ->and((bool) $row->is_bot)->toBeFalse()
        ->and($row->ip_hash)->toBeString()
        ->and($row->ip_hash)->not->toContain('203.0.113');
});

it('record() never throws when the visits table is missing', function (): void {
    // No table created — the insert must be swallowed, not propagated.
    expect(fn () => visitWriter()->record(chromeRequest(), new Response('', 200)))
        ->not->toThrow(Throwable::class);
});

function createVisitsTable(): void
{
    Schema::create('visits', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('session_id', 40)->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('tenant_id')->nullable();
        $table->string('tenant_type')->nullable();
        $table->string('panel', 40)->nullable();
        $table->string('route_name', 160)->nullable();
        $table->string('path', 2048);
        $table->char('method', 8);
        $table->unsignedSmallInteger('status');
        $table->string('referrer_host', 255)->nullable();
        $table->char('country_code', 2)->nullable();
        $table->char('ip_hash', 64)->nullable();
        $table->string('device_type', 20)->nullable();
        $table->string('browser', 40)->nullable();
        $table->string('platform', 40)->nullable();
        $table->boolean('is_bot')->default(false);
        $table->unsignedInteger('duration_ms')->nullable();
        $table->timestamp('created_at')->nullable();
    });
}

afterEach(function (): void {
    Schema::dropIfExists('visits');
});
