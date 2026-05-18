<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Events\SocialAccountMapping;
use Codenzia\FilamentPanelBase\Auth\Events\SocialUserLinked;
use Codenzia\FilamentPanelBase\Auth\Models\SocialAccount;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Tests\Support\SocialiteFake;
use Codenzia\FilamentPanelBase\Tests\Support\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * Covers the social-login flow end-to-end against the in-memory SQLite
 * schema. Socialite is mocked via the facade — no real OAuth round-trips.
 *
 * @see \Codenzia\FilamentPanelBase\Auth\Concerns\FindsOrCreatesFromSocialite
 * @see \Codenzia\FilamentPanelBase\Auth\Http\Controllers\OAuthController
 */
beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable()->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('social_accounts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('provider');
        $table->string('provider_id');
        $table->string('email')->nullable();
        $table->string('name')->nullable();
        $table->text('avatar')->nullable();
        $table->text('token')->nullable();
        $table->text('refresh_token')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
        $table->unique(['provider', 'provider_id']);
    });

    // Point the plugin at the test user model & enable a couple of providers.
    config()->set('filament-panel-base.user_model', TestUser::class);

    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->social_providers_enabled = ['google', 'github'];
    $settings->social_email_linking = 'require_login';
    $settings->social_trust_verified_email = true;
    $settings->credentials_mode = 'email';
    app()->instance(AuthenticationSettings::class, $settings);

    Auth::setDefaultDriver('web');
    config()->set('auth.providers.users.model', TestUser::class);

    Event::fake([SocialUserLinked::class, SocialAccountMapping::class]);
});

afterEach(function (): void {
    Schema::dropIfExists('social_accounts');
    Schema::dropIfExists('users');
});

function mockSocialiteUser(SocialiteFake $fake): void
{
    Socialite::shouldReceive('driver->user')->andReturn($fake);
}

it('redirect 404s when provider is not enabled', function (): void {
    $this->get('/oauth/twitter/redirect')->assertNotFound();
});

it('returns a redirect when provider is enabled', function (): void {
    Socialite::shouldReceive('driver->redirect')->andReturn(redirect('https://provider.example/auth'));

    $this->get('/oauth/google/redirect')
        ->assertRedirect('https://provider.example/auth');
});

it('creates a fresh user + social_accounts row on first sign-in', function (): void {
    mockSocialiteUser(new SocialiteFake(
        id: 'g_1',
        email: 'new@example.test',
        name: 'New User',
    ));

    $response = $this->get('/oauth/google/callback');

    expect(TestUser::query()->count())->toBe(1);
    expect(SocialAccount::query()->count())->toBe(1);

    $user = TestUser::query()->first();
    expect($user->email)->toBe('new@example.test');
    expect($user->email_verified_at)->not->toBeNull(); // provider asserted email_verified

    $link = SocialAccount::query()->first();
    expect($link->provider)->toBe('google');
    expect($link->provider_id)->toBe('g_1');
    expect($link->user_id)->toBe($user->id);

    $response->assertRedirect();

    Event::assertDispatched(SocialUserLinked::class, fn (SocialUserLinked $e): bool => $e->linked === true);
    Event::assertDispatched(SocialAccountMapping::class, fn (SocialAccountMapping $e): bool => $e->creatingUser === true);
});

it('returns the existing user on returning sign-in (provider+id match)', function (): void {
    $user = TestUser::create([
        'name' => 'Returning User',
        'email' => 'return@example.test',
        'password' => bcrypt('whatever'),
    ]);

    $user->socialAccounts()->create([
        'provider' => 'google',
        'provider_id' => 'g_2',
        'email' => 'return@example.test',
    ]);

    mockSocialiteUser(new SocialiteFake(id: 'g_2', email: 'return@example.test'));

    $this->get('/oauth/google/callback');

    expect(TestUser::query()->count())->toBe(1);
    expect(SocialAccount::query()->count())->toBe(1);

    Event::assertDispatched(SocialUserLinked::class, fn (SocialUserLinked $e): bool => $e->linked === false);
});

it('rejects email-match under require_login policy without creating the social link', function (): void {
    TestUser::create([
        'name' => 'Existing User',
        'email' => 'taken@example.test',
        'email_verified_at' => now(),
        'password' => bcrypt('whatever'),
    ]);

    mockSocialiteUser(new SocialiteFake(id: 'g_3', email: 'taken@example.test'));

    $response = $this->get('/oauth/google/callback');

    expect(SocialAccount::query()->count())->toBe(0);
    expect(TestUser::query()->count())->toBe(1);
    $response->assertRedirect(route('login'));

    Event::assertNotDispatched(SocialUserLinked::class);
});

it('links when trust_verified policy + both sides verified', function (): void {
    /** @var AuthenticationSettings $settings */
    $settings = app(AuthenticationSettings::class);
    $settings->social_email_linking = 'trust_verified';

    TestUser::create([
        'name' => 'Existing',
        'email' => 'verified@example.test',
        'email_verified_at' => now(),
        'password' => bcrypt('whatever'),
    ]);

    mockSocialiteUser(new SocialiteFake(
        id: 'g_4',
        email: 'verified@example.test',
        raw: ['email_verified' => true],
    ));

    $this->get('/oauth/google/callback');

    expect(SocialAccount::query()->count())->toBe(1);
    expect(TestUser::query()->count())->toBe(1);
});

it('rejects under trust_verified policy when provider email is not verified', function (): void {
    /** @var AuthenticationSettings $settings */
    $settings = app(AuthenticationSettings::class);
    $settings->social_email_linking = 'trust_verified';

    TestUser::create([
        'name' => 'Existing',
        'email' => 'unverified@example.test',
        'email_verified_at' => now(),
        'password' => bcrypt('whatever'),
    ]);

    mockSocialiteUser(new SocialiteFake(
        id: 'g_5',
        email: 'unverified@example.test',
        raw: ['email_verified' => false],
    ));

    $this->get('/oauth/google/callback');

    expect(SocialAccount::query()->count())->toBe(0);
});

it('rejects missing-email sign-in when credentials_mode requires email', function (): void {
    mockSocialiteUser(new SocialiteFake(id: 'g_6', email: null));

    $response = $this->get('/oauth/google/callback');

    expect(TestUser::query()->count())->toBe(0);
    $response->assertRedirect(route('login'));
});

it('flashes a translated error and redirects on InvalidStateException', function (): void {
    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    $response = $this->get('/oauth/google/callback');

    $response->assertRedirect(route('login'));
    expect(session('error'))->toBe(__('filament-panel-base::auth.oauth_invalid_state'));
});

it('flashes a generic translated error on other Socialite errors', function (): void {
    Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('boom'));

    $response = $this->get('/oauth/google/callback');

    $response->assertRedirect(route('login'));
    expect(session('error'))->toBe(
        __('filament-panel-base::auth.oauth_provider_error', ['provider' => 'Google'])
    );
});

it('throws LogicException when user model does not implement the contract', function (): void {
    config()->set('filament-panel-base.user_model', \stdClass::class);

    Socialite::shouldReceive('driver->user')->andReturn(new SocialiteFake);

    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/oauth/google/callback'))
        ->toThrow(LogicException::class);
});

it('allows mapping event subscribers to mutate user attributes before persistence', function (): void {
    // Replay Event::fake without faking SocialAccountMapping so listeners actually run.
    Event::fake([SocialUserLinked::class]);
    Event::listen(SocialAccountMapping::class, function (SocialAccountMapping $event): void {
        $event->userAttributes['name'] = 'OVERRIDDEN';
        $event->socialAccountAttributes['avatar'] = 'https://custom/avatar.png';
    });

    mockSocialiteUser(new SocialiteFake(id: 'g_7', email: 'map@example.test', name: 'Original'));

    $this->get('/oauth/google/callback');

    $user = TestUser::query()->first();
    expect($user->name)->toBe('OVERRIDDEN');

    $link = SocialAccount::query()->first();
    expect($link->avatar)->toBe('https://custom/avatar.png');
});

it('link flow attaches new SocialAccount to currently-authenticated user', function (): void {
    $user = TestUser::create([
        'name' => 'Already Signed In',
        'email' => 'signed@example.test',
        'email_verified_at' => now(),
        'password' => bcrypt('strong-pw'),
    ]);

    $this->be($user);

    // Step 1: hit redirect with link=1 — controller stores the link flag in session.
    Socialite::shouldReceive('driver->redirect')->andReturn(redirect('https://provider.example/auth'));
    $this->get('/oauth/github/redirect?link=1&return_to=/profile');

    // Step 2: the provider sends us back to the callback.
    mockSocialiteUser(new SocialiteFake(id: 'gh_1', email: 'github@example.test'));
    $response = $this->get('/oauth/github/callback');

    $response->assertRedirect('/profile');
    expect($user->fresh()->socialAccounts()->count())->toBe(1);
    expect($user->fresh()->socialAccounts()->first()->provider)->toBe('github');
});

it('rejects link flow when provider+id already belongs to a different user', function (): void {
    $other = TestUser::create([
        'name' => 'Other',
        'email' => 'other@example.test',
        'password' => bcrypt('x'),
    ]);
    $other->socialAccounts()->create(['provider' => 'github', 'provider_id' => 'gh_2']);

    $current = TestUser::create([
        'name' => 'Current',
        'email' => 'current@example.test',
        'password' => bcrypt('x'),
    ]);
    $this->be($current);

    Socialite::shouldReceive('driver->redirect')->andReturn(redirect('https://provider.example/auth'));
    $this->get('/oauth/github/redirect?link=1&return_to=/profile');

    mockSocialiteUser(new SocialiteFake(id: 'gh_2', email: 'other@example.test'));
    $this->get('/oauth/github/callback');

    expect($current->fresh()->socialAccounts()->count())->toBe(0);
    expect($other->fresh()->socialAccounts()->count())->toBe(1);
    expect(session('error'))->not->toBeNull();
});
