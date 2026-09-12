<?php

use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Codenzia\FilamentPanelBase\TwoFactor\Exceptions\TwoFactorUnavailableException;
use Codenzia\FilamentPanelBase\TwoFactor\Services\LoginChallengeDecider;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * PB-03 regression: an unreadable two-factor policy must not be mistaken for a
 * disabled one. There is no settings table in this suite, so resolving
 * TwoFactorSettings throws exactly the way a settings-store outage does.
 */
beforeEach(function (): void {
    $this->createUsersTable();
    config()->set('session.driver', 'array');
    config()->set('auth.providers.users.model', TwoFactorUser::class);

    $this->decider = new LoginChallengeDecider;
});

it('refuses to answer for an enrolled user when the policy cannot be read', function (): void {
    $user = TwoFactorUser::create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => bcrypt('secret-password'),
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    expect(fn (): bool => $this->decider->shouldChallenge($user))
        ->toThrow(TwoFactorUnavailableException::class);
});

it('lets an unenrolled user through when the policy cannot be read', function (): void {
    $user = TwoFactorUser::create([
        'name' => 'Ann',
        'email' => 'ann@example.com',
        'password' => bcrypt('secret-password'),
    ]);

    expect($this->decider->shouldChallenge($user))->toBeFalse();
});

it('lets a user model without the trait through when the policy cannot be read', function (): void {
    config()->set('auth.providers.users.model', PlainDeciderUser::class);

    $user = PlainDeciderUser::create(['name' => 'Cid', 'email' => 'cid@example.com']);

    expect($this->decider->shouldChallenge($user))->toBeFalse();
});

it('does not challenge when the policy is readable and deliberately disabled', function (): void {
    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->enabled = false;
    app()->instance(TwoFactorSettings::class, $settings);

    $user = TwoFactorUser::create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => bcrypt('secret-password'),
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    expect($this->decider->shouldChallenge($user))->toBeFalse();
});

it('challenges an enrolled user when the policy is readable and enabled', function (): void {
    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->enabled = true;
    $settings->remember_device = false;
    app()->instance(TwoFactorSettings::class, $settings);

    $user = TwoFactorUser::create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => bcrypt('secret-password'),
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    expect($this->decider->shouldChallenge($user))->toBeTrue();
});

/**
 * A user model with no two-factor trait at all — the "this application does
 * not use the module" case, which must keep signing in normally.
 */
class PlainDeciderUser extends AuthUser
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
