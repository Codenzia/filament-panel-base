<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Codenzia\FilamentPanelBase\Analytics\Services\IpAnonymizer;
use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;
use Codenzia\FilamentPanelBase\Analytics\Subscribers\AuthEventSubscriber;
use Codenzia\FilamentPanelBase\Auth\Events\OtpRequested;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The subscriber turns auth events into auth_events rows. Two invariants
 * matter most: (1) OTP targets (email/phone = PII) are HMAC'd, never stored
 * raw; (2) analytics failures must never bubble into the auth flow, and the
 * enabled/track_auth_events gates are honoured.
 */
function makeSubscriber(bool $enabled = true, bool $trackAuth = true): AuthEventSubscriber
{
    /** @var AnalyticsSettings $settings */
    $settings = test()->settingsStub(AnalyticsSettings::class);
    $settings->enabled = $enabled;
    $settings->track_auth_events = $trackAuth;
    $settings->ip_anonymization = 'truncate';

    return new AuthEventSubscriber($settings, new IpAnonymizer($settings));
}

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));

    Schema::create('auth_events', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('tenant_id')->nullable();
        $table->string('tenant_type')->nullable();
        $table->string('panel', 40)->nullable();
        $table->string('type', 40);
        $table->string('channel', 32)->nullable();
        $table->char('ip_hash', 64)->nullable();
        $table->char('country_code', 2)->nullable();
        $table->json('meta')->nullable();
        $table->timestamp('created_at')->nullable();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('auth_events');
});

it('HMACs the OTP target so raw PII is never persisted', function (): void {
    makeSubscriber()->onOtpRequested(new OtpRequested(
        target: 'user@example.test',
        channel: 'email',
        context: ['brand' => 'Acme', 'locale' => 'en'],
    ));

    $row = AuthEvent::query()->sole();

    expect($row->type)->toBe(AuthEvent::TYPE_OTP_REQUESTED)
        ->and($row->channel)->toBe('email')
        // The raw address must not appear anywhere in the stored meta…
        ->and(json_encode($row->meta))->not->toContain('user@example.test')
        // …and the target is the HMAC of the address under the app key.
        ->and($row->meta['target'])->toBe(
            hash_hmac('sha256', 'user@example.test', (string) config('app.key'))
        )
        // Whitelisted context passes through; nothing else does.
        ->and($row->meta['brand'])->toBe('Acme')
        ->and($row->meta['locale'])->toBe('en');
});

it('only keeps whitelisted OTP context keys (drops arbitrary payload)', function (): void {
    makeSubscriber()->onOtpRequested(new OtpRequested(
        target: '+962790000000',
        channel: 'sms',
        context: ['brand' => 'Acme', 'password' => 'secret', 'ip' => '1.2.3.4'],
    ));

    $meta = AuthEvent::query()->sole()->meta;

    expect($meta)->toHaveKeys(['target', 'brand'])
        ->and($meta)->not->toHaveKey('password')
        ->and($meta)->not->toHaveKey('ip');
});

it('writes nothing when analytics is disabled', function (): void {
    makeSubscriber(enabled: false)->onOtpRequested(
        new OtpRequested('user@example.test', 'email')
    );

    expect(AuthEvent::query()->count())->toBe(0);
});

it('writes nothing when track_auth_events is off', function (): void {
    makeSubscriber(trackAuth: false)->onOtpRequested(
        new OtpRequested('user@example.test', 'email')
    );

    expect(AuthEvent::query()->count())->toBe(0);
});

it('never lets an analytics insert failure break the auth flow', function (): void {
    // Drop the table so the insert throws — the handler must swallow it.
    Schema::dropIfExists('auth_events');

    $subscriber = makeSubscriber();

    expect(fn () => $subscriber->onOtpRequested(
        new OtpRequested('user@example.test', 'email')
    ))->not->toThrow(Throwable::class);
});
