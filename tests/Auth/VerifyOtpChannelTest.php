<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Concerns\HasPhoneNumber;
use Codenzia\FilamentPanelBase\Auth\Contracts\HasPhone;
use Codenzia\FilamentPanelBase\Auth\Livewire\VerifyOtp;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * PNB-017: verifying an OTP must stamp the channel that was actually verified.
 * An email-channel code marks the email verified (never the phone), and a
 * phone-channel code marks the phone verified (never the email).
 */
beforeEach(function (): void {
    $this->createUsersTable();
    Schema::table('users', function (Blueprint $table): void {
        $table->string('phone')->nullable();
        $table->timestamp('phone_verified_at')->nullable();
    });

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

    config()->set('session.driver', 'array');
    config()->set('auth.providers.users.model', OtpChannelUser::class);

    if (! Route::has('home')) {
        Route::get('/home', fn () => 'home')->name('home');
    }
});

function seedOtpChannelCode(OtpChannelUser $user, string $target, string $channel): void
{
    DB::table('otp_codes')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->getKey(),
        'target' => $target,
        'channel' => $channel,
        'code_hash' => Hash::make('123456'),
        'context' => json_encode([]),
        'attempts' => 0,
        'ip' => '127.0.0.1',
        'expires_at' => now()->addMinutes(10),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('marks the email verified (and not the phone) on an email-channel OTP (PNB-017)', function (): void {
    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->otp_driver = 'email';
    $settings->otp_code_length = 6;
    app()->instance(AuthenticationSettings::class, $settings);

    $user = OtpChannelUser::create([
        'name' => 'Ella',
        'email' => 'ella@example.com',
        'phone' => '+962791234567',
        'password' => bcrypt('secret-password'),
    ]);
    $this->actingAs($user);

    seedOtpChannelCode($user, 'ella@example.com', 'email');

    Livewire::test(VerifyOtp::class)
        ->set('code', '123456')
        ->call('verify')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->phone_verified_at)->toBeNull();
});

it('marks the phone verified (and not the email) on a phone-channel OTP (PNB-017)', function (): void {
    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->otp_driver = 'null';
    $settings->otp_code_length = 6;
    app()->instance(AuthenticationSettings::class, $settings);

    $user = OtpChannelUser::create([
        'name' => 'Faisal',
        'email' => 'faisal@example.com',
        'phone' => '+962791234567',
        'password' => bcrypt('secret-password'),
    ]);
    $this->actingAs($user);

    seedOtpChannelCode($user, '+962791234567', 'null');

    Livewire::test(VerifyOtp::class)
        ->set('code', '123456')
        ->call('verify')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->phone_verified_at)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull();
});

class OtpChannelUser extends AuthUser implements HasPhone, MustVerifyEmail
{
    use HasPhoneNumber;
    use MustVerifyEmailTrait;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];
}
