<?php

use Codenzia\FilamentPanelBase\Auth\Livewire\ManageSocialAccounts;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Auth\Support\UnusablePassword;
use Codenzia\FilamentPanelBase\Tests\Support\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * PB-07 regression: an account created by a provider has never been given a
 * password, so removing its only linked provider must be refused rather than
 * cheerfully reported as a success.
 */
beforeEach(function (): void {
    $this->createUsersTable();

    Schema::create('social_accounts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->index();
        $table->string('provider');
        $table->string('provider_id');
        $table->string('email')->nullable();
        $table->string('name')->nullable();
        $table->string('avatar')->nullable();
        $table->text('token')->nullable();
        $table->text('refresh_token')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    config()->set('auth.providers.users.model', TestUser::class);

    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->social_providers_enabled = ['google', 'github'];
    app()->instance(AuthenticationSettings::class, $settings);
});

afterEach(function (): void {
    Schema::dropIfExists('social_accounts');
});

function linkProvider(TestUser $user, string $provider): int
{
    return $user->socialAccounts()->create([
        'provider' => $provider,
        'provider_id' => $provider.'-1',
    ])->getKey();
}

it('refuses to remove the last provider from a provider-created account', function (): void {
    $user = TestUser::create([
        'name' => 'Ada',
        'email' => 'ada@acme.test',
        'password' => UnusablePassword::make(),
    ]);

    $accountId = linkProvider($user, 'google');

    Auth::login($user);

    Livewire::test(ManageSocialAccounts::class)->call('disconnect', $accountId);

    expect($user->socialAccounts()->count())->toBe(1);
});

it('allows removing the last provider once a real password exists', function (): void {
    $user = TestUser::create([
        'name' => 'Ada',
        'email' => 'ada@acme.test',
        'password' => bcrypt('a-password-she-chose'),
    ]);

    $accountId = linkProvider($user, 'google');

    Auth::login($user);

    Livewire::test(ManageSocialAccounts::class)->call('disconnect', $accountId);

    expect($user->socialAccounts()->count())->toBe(0);
});

it('allows removing one provider while another remains linked', function (): void {
    $user = TestUser::create([
        'name' => 'Ada',
        'email' => 'ada@acme.test',
        'password' => UnusablePassword::make(),
    ]);

    $accountId = linkProvider($user, 'google');
    linkProvider($user, 'github');

    Auth::login($user);

    Livewire::test(ManageSocialAccounts::class)->call('disconnect', $accountId);

    expect($user->socialAccounts()->count())->toBe(1);
});
