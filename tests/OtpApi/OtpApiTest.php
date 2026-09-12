<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\LaravelSms\Facades\Sms;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // The routes are gated at boot on config; register them at runtime here so
    // the module's controllers/requests/throttles can be exercised over HTTP.
    // The boot-time gate itself is covered by tests/Auth/OtpApiDisabledTest.php.
    Route::middleware((array) config('filament-panel-base.otp_api.middleware', ['api']))
        ->prefix((string) config('filament-panel-base.otp_api.prefix', 'api/pb'))
        ->name('filament-panel-base.otp-api.')
        ->group(__DIR__.'/../../routes/otp-api.php');

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

    // Bind an AuthenticationSettings stub so OtpService resolves without a
    // settings table. Property defaults on the class give sane TTL/throttle.
    $settings = (new ReflectionClass(AuthenticationSettings::class))->newInstanceWithoutConstructor();
    $settings->otp_driver = 'null';
    app()->instance(AuthenticationSettings::class, $settings);

    // The channels this endpoint is allowed to deliver through. Declared
    // explicitly because the suite drives the null transport, which is exactly
    // the driver a production endpoint must not expose.
    config()->set('filament-panel-base.otp_api.channels', ['null', 'twilio', 'email']);
});

it('rejects a channel the endpoint does not expose', function (): void {
    config()->set('filament-panel-base.otp_api.channels', ['email']);

    $this->postJson('/api/pb/otp/request', ['target' => '+962790000000', 'channel' => 'null'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('channel');

    expect(DB::table('otp_codes')->count())->toBe(0);
});

/**
 * Issue an OTP through the twilio channel under a faked Sms transport and
 * return the fake plus the extracted cleartext code (read from the SMS body,
 * never from the API response).
 */
function issueOtpAndReadCode(string $target): string
{
    $fake = Sms::fake();

    test()->postJson('/api/pb/otp/request', ['target' => $target, 'channel' => 'twilio'])
        ->assertStatus(202);

    preg_match('/\d{4,8}/', $fake->sent()[0]->body, $matches);

    return $matches[0];
}

it('issues an OTP and never returns the code', function (): void {
    Sms::fake();

    $response = $this->postJson('/api/pb/otp/request', [
        'target' => '+962790000000',
        'channel' => 'twilio',
    ]);

    $response->assertStatus(202)->assertJson(['status' => 'sent']);
    expect($response->json())->not->toHaveKey('code');
    expect(DB::table('otp_codes')->where('target', '+962790000000')->exists())->toBeTrue();
});

it('delivers the request through the laravel-sms transport (Sms::fake)', function (): void {
    $fake = Sms::fake();

    $this->postJson('/api/pb/otp/request', ['target' => '+962790000000', 'channel' => 'twilio'])
        ->assertStatus(202);

    Sms::assertSentTo('+962790000000');
    expect($fake->sent())->toHaveCount(1);
});

it('verifies a correct code', function (): void {
    $code = issueOtpAndReadCode('+962790000000');

    $this->postJson('/api/pb/otp/verify', [
        'target' => '+962790000000',
        'code' => $code,
        'channel' => 'twilio',
    ])->assertStatus(200)->assertJson(['verified' => true]);
});

it('rejects a wrong code with 422', function (): void {
    issueOtpAndReadCode('+962790000000');

    $this->postJson('/api/pb/otp/verify', [
        'target' => '+962790000000',
        'code' => '000000',
        'channel' => 'twilio',
    ])->assertStatus(422)->assertJson(['verified' => false]);
});

it('invokes the host token issuer on a successful verify', function (): void {
    config([
        'filament-panel-base.otp_api.token_issuer' => fn (string $target): array => ['token' => 'tok-'.$target],
    ]);

    $code = issueOtpAndReadCode('+962790000000');

    $this->postJson('/api/pb/otp/verify', [
        'target' => '+962790000000',
        'code' => $code,
        'channel' => 'twilio',
    ])->assertStatus(200)->assertJson([
        'verified' => true,
        'token' => 'tok-+962790000000',
    ]);
});

it('rejects invalid phone input with a validation error', function (): void {
    $this->postJson('/api/pb/otp/request', ['target' => '12345'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('target');
});

it('normalises national input to E.164 on phone register', function (): void {
    Sms::fake();

    $this->postJson('/api/pb/phone/register', ['phone' => '0790000000', 'channel' => 'null'])
        ->assertStatus(202)
        ->assertJson(['status' => 'sent', 'target' => '+962790000000']);
});

it('applies a per-IP throttle to the request endpoint', function (): void {
    // Distinct targets so the per-target OtpService throttle never fires — only
    // the per-IP route throttle (default 10/hr) should trip the 11th call.
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/pb/otp/request', [
            'target' => '+96279000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'channel' => 'null',
        ])->assertStatus(202);
    }

    $this->postJson('/api/pb/otp/request', ['target' => '+962790009999', 'channel' => 'null'])
        ->assertStatus(429);
});
