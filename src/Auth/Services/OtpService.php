<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Services;

use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\OtpDriver;
use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\OtpDriverManager;
use Codenzia\FilamentPanelBase\Auth\Events\OtpRequested;
use Codenzia\FilamentPanelBase\Auth\Events\OtpVerified;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Owns OTP code lifecycle:
 *  - Generate a numeric code, store its hash (never the cleartext) in the
 *    `otp_codes` table together with channel + expiry + ip.
 *  - Rate-limit issuance per (target + channel) to slow code-stuffing.
 *  - Verify a submitted code: constant-time match against the hash,
 *    increment attempts, delete on success or after max attempts.
 *
 * Hosts call this service directly (or through Livewire components). The
 * actual transport (email, WhatsApp, Twilio, Vonage, Null) is handled by
 * an OtpDriver resolved from OtpDriverManager.
 */
class OtpService
{
    /**
     * A valid bcrypt hash used purely to spend a comparable amount of time on
     * the verify() miss path. Keeps the response time of "no active OTP" close
     * to "wrong code" so timing can't be used to probe whether a code exists.
     */
    private const TIMING_EQUALISER_HASH = '$2y$12$zBd5I2yfT/ypZrRrdEiKoe4iNNn3PmVyRgMAwiDIEhbw/kLvYJAvW';

    public function __construct(
        private readonly OtpDriverManager $drivers,
        private readonly AuthenticationSettings $settings,
    ) {}

    /**
     * Generate a new OTP, persist it, and dispatch it through the resolved
     * driver. Returns the generated cleartext code (drivers use it; callers
     * generally ignore the return value).
     *
     * @param  array<string, mixed>  $context  Forwarded to the driver and the OtpRequested event.
     */
    public function send(string $target, ?string $driver = null, array $context = [], int|string|null $userId = null): string
    {
        $driverName = $driver ?? $this->settings->otp_driver;

        $this->ensureNotRateLimited($target, $driverName);

        $code = $this->generateCode();

        // Atomic replace: a previous delete()+insert() pair left a window in
        // which two concurrent sends for the same (target, channel) both
        // deleted then both inserted, tripping the unique index into a 500.
        // upsert() issues a single INSERT ... ON CONFLICT so the DB serialises
        // the race and the latest code always wins.
        DB::table('otp_codes')->upsert(
            [[
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'target' => $target,
                'channel' => $driverName,
                'code_hash' => Hash::make($code),
                'context' => json_encode($context, JSON_THROW_ON_ERROR),
                'attempts' => 0,
                'ip' => request()?->ip(),
                'expires_at' => now()->addMinutes($this->settings->otp_ttl_minutes),
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['target', 'channel'],
            ['user_id', 'code_hash', 'context', 'attempts', 'ip', 'expires_at', 'updated_at'],
        );

        /** @var OtpDriver $transport */
        $transport = $this->drivers->driver($driverName);
        $transport->send($target, $code, $context);

        RateLimiter::hit($this->rateLimitKey($target, $driverName), 60);

        event(new OtpRequested($target, $driverName, $context));

        return $code;
    }

    /**
     * Validate a submitted code. Returns true on success and deletes the
     * stored record; returns false otherwise. Bumps the attempt counter and
     * deletes the record once max attempts is reached.
     */
    public function verify(string $target, string $code, ?string $driver = null, int|string|null $userId = null): bool
    {
        $driverName = $driver ?? $this->settings->otp_driver;

        // Read + Hash::check + attempt mutation must be atomic so concurrent
        // requests cannot undercount attempts (check-then-increment race).
        return DB::transaction(function () use ($target, $driverName, $code, $userId): bool {
            $query = DB::table('otp_codes')
                ->where('target', $target)
                ->where('channel', $driverName)
                ->where('expires_at', '>', now());

            // When an owner is supplied, bind verification to that user so a
            // code issued for one account can't be consumed by another. When no
            // owner is supplied, restrict to codes that are themselves
            // unbound — otherwise a caller omitting the id could consume a
            // user-bound code (PNB-032).
            if ($userId !== null) {
                $query->where('user_id', $userId);
            } else {
                $query->whereNull('user_id');
            }

            $record = $query->lockForUpdate()->first();

            if (! $record) {
                // Spend the same order-of-magnitude of time as the hit path so
                // response timing does not reveal whether an active OTP exists
                // for this target (PNB-031).
                Hash::check($code, self::TIMING_EQUALISER_HASH);

                return false;
            }

            if (! Hash::check($code, $record->code_hash)) {
                $maxAttempts = (int) config('filament-panel-base.auth.otp.max_attempts', 5);

                if (($record->attempts + 1) >= $maxAttempts) {
                    DB::table('otp_codes')->where('id', $record->id)->delete();
                } else {
                    DB::table('otp_codes')->where('id', $record->id)->increment('attempts');
                }

                return false;
            }

            DB::table('otp_codes')->where('id', $record->id)->delete();

            event(new OtpVerified($target, $driverName));

            return true;
        });
    }

    /**
     * Hash-based rate limiter key — does not leak the cleartext target.
     */
    private function rateLimitKey(string $target, string $driver): string
    {
        // Normalise the target (email/phone) so case/whitespace variants can't
        // each open a fresh issuance bucket for the same recipient.
        $normalizedTarget = mb_strtolower(trim($target));

        return 'fpb-otp:'.hash_hmac('sha256', $normalizedTarget.'|'.$driver, (string) config('app.key'));
    }

    private function ensureNotRateLimited(string $target, string $driver): void
    {
        $key = $this->rateLimitKey($target, $driver);
        $perMinute = $this->settings->throttle_per_minute;

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new \RuntimeException(__('filament-panel-base::auth.otp_rate_limited', ['seconds' => $retryAfter]));
        }
    }

    private function generateCode(): string
    {
        $length = max(4, min(8, $this->settings->otp_code_length));

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
