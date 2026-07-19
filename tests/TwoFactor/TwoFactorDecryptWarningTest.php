<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PNB-024: when an encrypted 2FA column cannot be decrypted (e.g. APP_KEY was
 * rotated), the accessor still falls back to the raw value but must log a
 * warning so the breakage is not silently masked.
 */
beforeEach(function (): void {
    $this->createUsersTable();

    // The warn-once guard is a per-request static; reset it so each test starts
    // from a clean slate regardless of ordering.
    $property = new ReflectionProperty(TwoFactorUser::class, 'twoFactorDecryptWarnings');
    $property->setAccessible(true);
    $property->setValue(null, []);
});

it('logs a warning when a two-factor secret cannot be decrypted (PNB-024)', function (): void {
    Log::spy();

    $user = TwoFactorUser::create([
        'name' => 'Grace',
        'email' => 'grace@example.com',
        'password' => bcrypt('secret-password'),
    ]);

    // Write ciphertext the current APP_KEY cannot decrypt.
    DB::table('users')->where('id', $user->getKey())->update([
        'two_factor_secret' => 'not-a-valid-payload',
    ]);

    $fresh = TwoFactorUser::find($user->getKey());
    $value = $fresh->two_factor_secret;

    expect($value)->toBe('not-a-valid-payload');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'two_factor_secret'))
        ->atLeast()->once();
});

it('only warns once per column per request (PNB-024)', function (): void {
    Log::spy();

    $user = TwoFactorUser::create([
        'name' => 'Heidi',
        'email' => 'heidi@example.com',
        'password' => bcrypt('secret-password'),
    ]);
    DB::table('users')->where('id', $user->getKey())->update([
        'two_factor_secret' => 'still-not-valid',
    ]);

    $fresh = TwoFactorUser::find($user->getKey());
    // Read the accessor several times — the warning must fire only once.
    $fresh->two_factor_secret;
    $fresh->two_factor_secret;
    $fresh->two_factor_secret;

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'two_factor_secret'))
        ->once();
});
