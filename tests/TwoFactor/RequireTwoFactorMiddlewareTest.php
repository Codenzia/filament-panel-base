<?php

use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Codenzia\FilamentPanelBase\TwoFactor\Http\Middleware\RequireTwoFactor;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FA\Google2FA;

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

it('redirects unenrolled required-role users to the configured enrolment route, never the challenge (PNB-002)', function (): void {
    Route::get('/enrol-2fa', fn () => 'enrol')->name('profile.security');
    config()->set('filament-panel-base.two_factor.enrolment_route', 'profile.security');

    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['admin'];
    Auth::setUser($user);

    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->isRedirect())->toBeTrue();
    // The enrolment destination — NOT the challenge page (which would loop).
    expect($response->getTargetUrl())->toContain('enrol-2fa');
    expect($response->getTargetUrl())->not->toContain('two-factor-challenge');
});

it('lets the enrolment route itself through so it is reachable (no loop) (PNB-002)', function (): void {
    Route::get('/enrol-2fa', fn () => 'enrol')->name('profile.security');
    config()->set('filament-panel-base.two_factor.enrolment_route', 'profile.security');

    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['admin'];
    Auth::setUser($user);

    $request = Request::create('/enrol-2fa');
    // Refresh name lookups so getByName resolves the just-registered route.
    Route::getRoutes()->refreshNameLookups();
    $request->setRouteResolver(fn () => Route::getRoutes()->getByName('profile.security'));

    $response = $this->middleware->handle($request, fn () => response('ok'));

    // The unenrolled admin can actually reach the enrolment page.
    expect($response->getContent())->toBe('ok');
});

it('fails open (no lockout loop) when no enrolment route is configured (PNB-002)', function (): void {
    config()->set('filament-panel-base.two_factor.enrolment_route', null);

    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['admin'];
    Auth::setUser($user);

    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    // No reachable enrolment target => let the request through rather than
    // trap the user in an infinite redirect. Better a temporary enforcement
    // gap than a total lockout.
    expect($response->getContent())->toBe('ok');
});

it('fails open when the configured enrolment route name does not resolve (PNB-002)', function (): void {
    config()->set('filament-panel-base.two_factor.enrolment_route', 'does.not.exist');

    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['admin'];
    Auth::setUser($user);

    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
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
    $g = new Google2FA;
    $user->confirmTwoFactor($g->getCurrentOtp($user->two_factor_secret));
    Auth::setUser($user);

    $request = Request::create('/dashboard');
    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('never redirects an unenrolled admin back onto the challenge route', function (): void {
    // The challenge page bounces authenticated-but-unpending users to login,
    // which bounces them home — the loop PNB-002 fixed. The middleware must
    // no longer treat the challenge as an enrolment destination.
    Route::get('/enrol-2fa', fn () => 'enrol')->name('profile.security');
    config()->set('filament-panel-base.two_factor.enrolment_route', 'profile.security');

    $user = RoleAwareTwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    $user->roles = ['admin'];
    Auth::setUser($user);

    $request = Request::create('/two-factor-challenge');
    Route::dispatch($request);
    $request->setRouteResolver(fn () => Route::getRoutes()->getByName('two-factor.challenge'));

    $response = $this->middleware->handle($request, fn () => response('ok'));

    // On the challenge route the unenrolled admin is redirected to enrolment,
    // not left on (or bounced around) the challenge page.
    expect($response->isRedirect())->toBeTrue();
    expect($response->getTargetUrl())->toContain('enrol-2fa');
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
