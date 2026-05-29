<?php

use Codenzia\FilamentPanelBase\TwoFactor\Http\Middleware\RequireTwoFactor;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Test user with a stub `hasAnyRole()` so we don't need spatie/laravel-permission.
 */
class RoleAwareTwoFactorUser extends TwoFactorUser
{
    /** @var array<int, string> */
    public array $roles = [];

    public function hasAnyRole(array $roles): bool
    {
        return ! empty(array_intersect($this->roles, $roles));
    }
}

beforeEach(function (): void {
    $this->createUsersTable();

    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->enabled = true;
    $settings->require_for_roles = ['admin'];
    app()->instance(TwoFactorSettings::class, $settings);

    // Register the challenge route so the middleware's redirect target resolves.
    Route::get('/two-factor-challenge', fn () => 'challenge')
        ->name('two-factor.challenge');

    $this->middleware = new RequireTwoFactor;
});

it('allows guests through', function (): void {
    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('allows users without a required role through', function (): void {
    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['editor'];
    Auth::setUser($user);

    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('redirects users in a required role who lack 2FA enrolment', function (): void {
    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['admin'];
    Auth::setUser($user);

    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->isRedirect())->toBeTrue();
    expect($response->getTargetUrl())->toContain('two-factor-challenge');
});

it('allows users with confirmed 2FA through', function (): void {
    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->enabled = true;
    $settings->require_for_roles = ['admin'];
    $settings->recovery_code_count = 4;
    $settings->digits = 6;
    $settings->period = 30;
    $settings->window = 1;
    app()->instance(TwoFactorSettings::class, $settings);

    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['admin'];
    $user->generateTwoFactorSecret();
    $g = new \PragmaRX\Google2FA\Google2FA;
    $user->confirmTwoFactor($g->getCurrentOtp($user->two_factor_secret));
    Auth::setUser($user);

    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('allows the challenge route itself through (no redirect loop)', function (): void {
    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['admin'];
    Auth::setUser($user);

    $request = Request::create('/two-factor-challenge');
    // Force the route to be matched so name() resolves.
    Route::dispatch($request);
    $request->setRouteResolver(fn () => Route::getRoutes()->getByName('two-factor.challenge'));

    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('allows through when settings module is disabled', function (): void {
    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->enabled = false;
    $settings->require_for_roles = ['admin'];
    app()->instance(TwoFactorSettings::class, $settings);

    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['admin'];
    Auth::setUser($user);

    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('allows through when require_for_roles is empty', function (): void {
    $settings = $this->settingsStub(TwoFactorSettings::class);
    $settings->enabled = true;
    $settings->require_for_roles = [];
    app()->instance(TwoFactorSettings::class, $settings);

    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['admin'];
    Auth::setUser($user);

    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('does not lock out a user model without hasAnyRole', function (): void {
    $user = TwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    Auth::setUser($user);

    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});
