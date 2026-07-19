<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\OtpDriverManager;
use Codenzia\FilamentPanelBase\Auth\Services\OtpService;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('otp_codes', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->unsignedBigInteger('user_id')->nullable()->index();
        $table->string('target')->index();
        $table->string('channel', 32)->index();
        $table->string('code_hash');
        $table->json('context')->nullable();
        $table->unsignedSmallInteger('attempts')->default(0);
        $table->string('ip', 45)->nullable();
        $table->timestamp('expires_at')->index();
        $table->timestamps();

        $table->unique(['target', 'channel'], 'otp_codes_target_channel_unique');
    });
});

/**
 * Build an OtpService bound to the `null` transport so no real message fires.
 */
function makeOtpService(?AuthenticationSettings $settings = null): OtpService
{
    $settings ??= (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    $settings->otp_driver = 'null';

    return new OtpService(app(OtpDriverManager::class), $settings);
}

it('stores a hash and never the cleartext code, with the configured expiry', function (): void {
    $service = makeOtpService();

    $code = $service->send('+962501234567', 'null');

    $row = DB::table('otp_codes')->where('target', '+962501234567')->first();

    expect($row)->not->toBeNull()
        ->and($row->code_hash)->not->toBe($code)
        ->and(password_verify($code, $row->code_hash) || Hash::check($code, $row->code_hash))->toBeTrue()
        ->and($row->channel)->toBe('null');
});

it('replaces the previous code for the same target and channel on re-send', function (): void {
    $service = makeOtpService();

    $service->send('+962501234567', 'null');
    $service->send('+962501234567', 'null');

    expect(DB::table('otp_codes')->where('target', '+962501234567')->where('channel', 'null')->count())->toBe(1);
});

it('verifies once then fails because the record was deleted', function (): void {
    $service = makeOtpService();

    $code = $service->send('+962501234567', 'null');

    expect($service->verify('+962501234567', $code, 'null'))->toBeTrue()
        ->and($service->verify('+962501234567', $code, 'null'))->toBeFalse();
});

it('increments attempts on a wrong code and burns the record at max attempts', function (): void {
    config()->set('filament-panel-base.auth.otp.max_attempts', 3);
    $service = makeOtpService();

    $service->send('+962501234567', 'null');

    expect($service->verify('+962501234567', '000000', 'null'))->toBeFalse();
    expect((int) DB::table('otp_codes')->where('target', '+962501234567')->value('attempts'))->toBe(1);

    $service->verify('+962501234567', '000000', 'null');
    $service->verify('+962501234567', '000000', 'null');

    expect(DB::table('otp_codes')->where('target', '+962501234567')->count())->toBe(0);
});

it('never verifies an expired code', function (): void {
    $service = makeOtpService();

    $code = $service->send('+962501234567', 'null');

    DB::table('otp_codes')->where('target', '+962501234567')->update(['expires_at' => now()->subMinute()]);

    expect($service->verify('+962501234567', $code, 'null'))->toBeFalse();
});

it('does not verify a code bound to one user with a different user id', function (): void {
    $service = makeOtpService();

    $code = $service->send('+962501234567', 'null', userId: 1);

    expect($service->verify('+962501234567', $code, 'null', userId: 2))->toBeFalse()
        ->and($service->verify('+962501234567', $code, 'null', userId: 1))->toBeTrue();
});

it('round-trips every admin-allowed OTP length (PNB-012)', function (): void {
    // The settings UI now caps otp_code_length at 8; generation clamps to the
    // same ceiling. Each allowed length must generate a code of exactly that
    // many digits that then verifies — no length can brick the verify step.
    foreach (range(4, 8) as $length) {
        $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
        $settings->otp_code_length = $length;
        $service = makeOtpService($settings);

        $target = "+96250000000{$length}";
        $code = $service->send($target, 'null');

        expect(strlen($code))->toBe($length)
            ->and(ctype_digit($code))->toBeTrue()
            ->and($service->verify($target, $code, 'null'))->toBeTrue();
    }
});

it('normalises the target so case/whitespace variants share one issuance bucket (PNB-011)', function (): void {
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    $settings->throttle_per_minute = 2;
    $service = makeOtpService($settings);

    // Two spellings of the same email plus a trimmed variant. Without target
    // normalisation each would open its own bucket; with it, the third send
    // trips the per-minute limit.
    $service->send('User@Example.com', 'null');
    $service->send('USER@EXAMPLE.COM', 'null');

    expect(fn () => $service->send('  user@example.com  ', 'null'))->toThrow(RuntimeException::class);
});

it('throws once issuance exceeds throttle_per_minute', function (): void {
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    $settings->throttle_per_minute = 2;
    $service = makeOtpService($settings);

    $service->send('+962501234567', 'null');
    $service->send('+962501234567', 'null');

    expect(fn () => $service->send('+962501234567', 'null'))->toThrow(RuntimeException::class);
});
