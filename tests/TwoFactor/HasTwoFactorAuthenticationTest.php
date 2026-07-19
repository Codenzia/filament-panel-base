<?php

use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Codenzia\FilamentPanelBase\TwoFactor\Events\RecoveryCodeUsed;
use Codenzia\FilamentPanelBase\TwoFactor\Events\TwoFactorDisabled;
use Codenzia\FilamentPanelBase\TwoFactor\Events\TwoFactorEnabled;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    $this->createUsersTable();

    // Bind a usable settings stub instead of hitting the missing settings table.
    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->digits = 6;
    $settings->period = 30;
    $settings->window = 1;
    $settings->recovery_code_count = 8;
    $settings->issuer = 'TestApp';
    app()->instance(TwoFactorSettings::class, $settings);

    $this->user = TwoFactorUser::create(['name' => 'Alice', 'email' => 'a@b.com', 'password' => 'x']);
});

it('reports 2FA disabled by default', function (): void {
    expect($this->user->hasTwoFactorEnabled())->toBeFalse();
});

it('generates a secret and recovery codes on enrolment', function (): void {
    $codes = $this->user->generateTwoFactorSecret();

    expect($codes)->toHaveCount(8);
    foreach ($codes as $code) {
        expect($code)->toMatch('/^[A-Za-z0-9]{10}-[A-Za-z0-9]{10}$/');
    }

    $this->user->refresh();
    expect($this->user->getRawOriginal('two_factor_secret'))->not->toBeEmpty();
    expect($this->user->getRawOriginal('two_factor_recovery_codes'))->not->toBeEmpty();
    expect($this->user->two_factor_confirmed_at)->toBeNull();
});

it('encrypts the stored secret at rest', function (): void {
    $this->user->generateTwoFactorSecret();
    $this->user->refresh();

    $raw = $this->user->getRawOriginal('two_factor_secret');
    $decrypted = $this->user->two_factor_secret;

    expect($raw)->not->toBe($decrypted);
    expect($decrypted)->toMatch('/^[A-Z2-7]+$/');
});

it('hashes stored recovery codes', function (): void {
    $plaintextCodes = $this->user->generateTwoFactorSecret();
    $this->user->refresh();

    $stored = $this->user->two_factor_recovery_codes;

    expect($stored)->toHaveCount(8);
    foreach ($stored as $hash) {
        // Bcrypt prefix; no plaintext in DB.
        expect($hash)->toStartWith('$2');
        expect(in_array($hash, $plaintextCodes, true))->toBeFalse();
    }
});

it('confirms enrolment when a valid TOTP is submitted', function (): void {
    Event::fake();

    $this->user->generateTwoFactorSecret();
    $secret = $this->user->two_factor_secret;

    $g = new Google2FA;
    $code = $g->getCurrentOtp($secret);

    expect($this->user->confirmTwoFactor($code))->toBeTrue();

    $this->user->refresh();
    expect($this->user->hasTwoFactorEnabled())->toBeTrue();
    expect($this->user->two_factor_confirmed_at)->not->toBeNull();

    Event::assertDispatched(TwoFactorEnabled::class);
});

it('rejects an invalid confirmation code', function (): void {
    $this->user->generateTwoFactorSecret();

    expect($this->user->confirmTwoFactor('000000'))->toBeFalse();
    expect($this->user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('verifies a TOTP code at challenge time', function (): void {
    $this->user->generateTwoFactorSecret();
    $g = new Google2FA;
    $code = $g->getCurrentOtp($this->user->two_factor_secret);

    expect($this->user->verifyTwoFactorCode($code))->toBeTrue();
});

it('consumes a recovery code on use', function (): void {
    Event::fake();

    $codes = $this->user->generateTwoFactorSecret();

    expect($this->user->verifyTwoFactorCode($codes[0]))->toBeTrue();

    $this->user->refresh();
    expect($this->user->two_factor_recovery_codes)->toHaveCount(7);

    // Same code no longer works
    expect($this->user->verifyTwoFactorCode($codes[0]))->toBeFalse();

    Event::assertDispatched(RecoveryCodeUsed::class);
});

it('consumes a recovery code exactly once under the row lock (PNB-008)', function (): void {
    Event::fake();

    $codes = $this->user->generateTwoFactorSecret();

    // Two consumptions of the same code: the first succeeds and removes exactly
    // one hash from the list; the second finds nothing left to match and fails.
    // The transaction + lockForUpdate is what stops two racing consumers from
    // both reading the pre-consumption list and both succeeding.
    expect($this->user->verifyTwoFactorCode($codes[0]))->toBeTrue();
    expect($this->user->verifyTwoFactorCode($codes[0]))->toBeFalse();

    $this->user->refresh();
    expect($this->user->two_factor_recovery_codes)->toHaveCount(7);

    // Exactly one RecoveryCodeUsed event — not two.
    Event::assertDispatchedTimes(RecoveryCodeUsed::class, 1);
});

it('replaces recovery codes on regenerate', function (): void {
    $oldCodes = $this->user->generateTwoFactorSecret();
    $newCodes = $this->user->replaceRecoveryCodes();

    expect($newCodes)->toHaveCount(8);
    expect($newCodes)->not->toBe($oldCodes);

    expect($this->user->verifyTwoFactorCode($oldCodes[0]))->toBeFalse();
    expect($this->user->verifyTwoFactorCode($newCodes[0]))->toBeTrue();
});

it('disables 2FA and fires event', function (): void {
    Event::fake();

    $this->user->generateTwoFactorSecret();
    $g = new Google2FA;
    $this->user->confirmTwoFactor($g->getCurrentOtp($this->user->two_factor_secret));

    $this->user->disableTwoFactor();
    $this->user->refresh();

    expect($this->user->hasTwoFactorEnabled())->toBeFalse();
    expect($this->user->getRawOriginal('two_factor_secret'))->toBeNull();
    expect($this->user->getRawOriginal('two_factor_recovery_codes'))->toBeNull();
    expect($this->user->two_factor_confirmed_at)->toBeNull();

    Event::assertDispatched(TwoFactorDisabled::class);
});

it('does not fire TwoFactorDisabled when 2FA was never enabled', function (): void {
    Event::fake();

    $this->user->disableTwoFactor();

    Event::assertNotDispatched(TwoFactorDisabled::class);
});
