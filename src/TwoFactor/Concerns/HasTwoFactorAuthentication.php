<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Concerns;

use Codenzia\FilamentPanelBase\TwoFactor\Events\RecoveryCodeUsed;
use Codenzia\FilamentPanelBase\TwoFactor\Events\TwoFactorDisabled;
use Codenzia\FilamentPanelBase\TwoFactor\Events\TwoFactorEnabled;
use Codenzia\FilamentPanelBase\TwoFactor\Services\RecoveryCodeGenerator;
use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorAuthenticator;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Drop into the host's `App\Models\User`:
 *
 *     class User extends Authenticatable {
 *         use HasTwoFactorAuthentication;
 *     }
 *
 * Expects the three columns added by the package's auto-loaded migration:
 *   - two_factor_secret           (text, encrypted at rest via accessor)
 *   - two_factor_recovery_codes   (text, JSON-encoded hashes, encrypted)
 *   - two_factor_confirmed_at     (timestamp)
 *
 * Column names match Laravel Fortify exactly so data is portable both
 * directions without a backfill.
 */
trait HasTwoFactorAuthentication
{
    /**
     * Tracks which encrypted 2FA columns have already logged a decrypt-failure
     * warning this request, so the log isn't flooded when many rows are read.
     *
     * @var array<string, bool>
     */
    private static array $twoFactorDecryptWarnings = [];

    /**
     * Log a one-time warning when an encrypted 2FA column cannot be decrypted
     * and the accessor falls back to the raw stored value. Most often this
     * means APP_KEY was rotated without re-encrypting the secrets — silently
     * treating ciphertext as plaintext would mask that breakage, so surface it
     * (once per column per request) while still failing soft.
     */
    protected static function warnAboutTwoFactorDecryptFailure(string $column): void
    {
        if (isset(self::$twoFactorDecryptWarnings[$column])) {
            return;
        }

        self::$twoFactorDecryptWarnings[$column] = true;

        Log::warning('filament-panel-base: could not decrypt '.$column.'; falling back to the raw stored value. This usually means APP_KEY was rotated without re-encrypting two-factor data.');
    }

    /**
     * Decrypted secret accessor / encrypting mutator. Falls back to raw
     * string when the value is already plaintext (covers legacy seeders
     * that bypass the mutator).
     */
    protected function twoFactorSecret(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable) {
                    static::warnAboutTwoFactorDecryptFailure('two_factor_secret');

                    return $value;
                }
            },
            set: fn (?string $value): ?string => $value === null || $value === ''
                ? null
                : Crypt::encryptString($value),
        );
    }

    /**
     * Decrypted recovery-code list (always returned hashed). Stored as an
     * encrypted JSON string. Returns an empty array when 2FA has never
     * been enabled.
     *
     * @return Attribute<array<int, string>, array<int, string>>
     */
    protected function twoFactorRecoveryCodes(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): array {
                if ($value === null || $value === '') {
                    return [];
                }

                try {
                    $decrypted = Crypt::decryptString($value);
                } catch (\Throwable) {
                    static::warnAboutTwoFactorDecryptFailure('two_factor_recovery_codes');

                    $decrypted = $value;
                }

                $decoded = json_decode($decrypted, true);

                return is_array($decoded) ? array_values($decoded) : [];
            },
            set: fn (array $codes): ?string => empty($codes)
                ? null
                : Crypt::encryptString((string) json_encode(array_values($codes))),
        );
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->getRawOriginal('two_factor_secret'))
            && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Provision a fresh secret + plaintext recovery codes. The secret is
     * persisted immediately; the user still needs to verify a code from
     * their authenticator app to flip `two_factor_confirmed_at`.
     *
     * Returns the *plaintext* recovery codes so the caller can show them
     * to the user once.
     *
     * @return array<int, string>
     */
    public function generateTwoFactorSecret(): array
    {
        /** @var TwoFactorAuthenticator $auth */
        $auth = app(TwoFactorAuthenticator::class);
        /** @var RecoveryCodeGenerator $gen */
        $gen = app(RecoveryCodeGenerator::class);
        /** @var TwoFactorSettings $settings */
        $settings = app(TwoFactorSettings::class);

        $this->two_factor_secret = $auth->generateSecret();

        $plaintextCodes = $gen->generate($settings->recovery_code_count);
        $this->two_factor_recovery_codes = array_map(
            static fn (string $code): string => Hash::make($code),
            $plaintextCodes,
        );

        $this->two_factor_confirmed_at = null;
        $this->save();

        return $plaintextCodes;
    }

    /**
     * Confirm enrolment by verifying a TOTP code from the user's app.
     * On success, flips `two_factor_confirmed_at` and emits TwoFactorEnabled.
     */
    public function confirmTwoFactor(string $code): bool
    {
        /** @var TwoFactorAuthenticator $auth */
        $auth = app(TwoFactorAuthenticator::class);

        $secret = $this->two_factor_secret;

        if (empty($secret) || ! $auth->verify($secret, $code)) {
            return false;
        }

        $this->two_factor_confirmed_at = now();
        $this->save();

        event(new TwoFactorEnabled($this));

        return true;
    }

    /**
     * Verify a code during the post-login challenge. Accepts either a
     * TOTP code or a recovery code. Returns true on success; used
     * recovery codes are consumed (removed from the stored list).
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        /** @var TwoFactorAuthenticator $auth */
        $auth = app(TwoFactorAuthenticator::class);

        $secret = $this->two_factor_secret;

        if (! empty($secret) && $auth->verify($secret, $code, guardReplay: true)) {
            return true;
        }

        return $this->consumeRecoveryCode($code);
    }

    /**
     * Issue a fresh batch of recovery codes, replacing the existing ones.
     *
     * @return array<int, string>
     */
    public function replaceRecoveryCodes(): array
    {
        /** @var RecoveryCodeGenerator $gen */
        $gen = app(RecoveryCodeGenerator::class);
        /** @var TwoFactorSettings $settings */
        $settings = app(TwoFactorSettings::class);

        $plaintextCodes = $gen->generate($settings->recovery_code_count);
        $this->two_factor_recovery_codes = array_map(
            static fn (string $code): string => Hash::make($code),
            $plaintextCodes,
        );
        $this->save();

        return $plaintextCodes;
    }

    /**
     * Current server-side nonce mixed into the "remember this device, skip
     * 2FA" cookie. Empty string until first rotated — that's fine, the cookie
     * is still bound to the secret + APP_KEY.
     */
    public function twoFactorRememberToken(): string
    {
        return (string) ($this->getAttribute('two_factor_remember_token') ?? '');
    }

    /**
     * Rotate the nonce so every outstanding remember-device cookie for this
     * user stops validating. Called on "log out everywhere", device revoke,
     * and when 2FA is disabled.
     */
    public function rotateTwoFactorRememberToken(): void
    {
        $this->two_factor_remember_token = Str::random(64);
        $this->save();
    }

    public function disableTwoFactor(): void
    {
        $wasEnabled = $this->hasTwoFactorEnabled();

        $this->two_factor_secret = null;
        $this->two_factor_recovery_codes = [];
        $this->two_factor_confirmed_at = null;
        $this->two_factor_remember_token = Str::random(64);
        $this->save();

        if ($wasEnabled) {
            event(new TwoFactorDisabled($this));
        }
    }

    /**
     * Hash-compare and consume a recovery code. Single-use semantics —
     * the matching hash is removed from the persisted list.
     */
    protected function consumeRecoveryCode(string $code): bool
    {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        // Serialise the read-modify-write of the hashed list: two parallel
        // challenges submitting the same recovery code would otherwise both
        // read the pre-consumption list and both succeed. Re-fetch this row
        // FOR UPDATE inside a transaction so concurrent consumers queue.
        return DB::transaction(function () use ($code): bool {
            /** @var static|null $locked */
            $locked = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return false;
            }

            $remaining = [];
            $matched = false;

            foreach ($locked->two_factor_recovery_codes as $hash) {
                if (! $matched && Hash::check($code, $hash)) {
                    $matched = true;

                    continue;
                }

                $remaining[] = $hash;
            }

            if (! $matched) {
                return false;
            }

            $locked->two_factor_recovery_codes = $remaining;
            $locked->save();

            // Keep the in-memory instance the caller holds consistent with
            // the row we just persisted under the lock.
            $this->two_factor_recovery_codes = $remaining;
            $this->syncOriginalAttribute('two_factor_recovery_codes');

            event(new RecoveryCodeUsed($this));

            return true;
        });
    }
}
