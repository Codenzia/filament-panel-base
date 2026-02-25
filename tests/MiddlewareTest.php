<?php

use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Codenzia\FilamentPanelBase\Middleware\EnsureUserApproved;
use Codenzia\FilamentPanelBase\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ─── SetLocale ───────────────────────────────────────────

it('sets locale from session', function () {
    config(['filament-panel-base.locale.available' => ['en', 'ar']]);

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));
    session(['locale' => 'ar']);

    $middleware = new SetLocale;
    $middleware->handle($request, fn () => new \Illuminate\Http\Response);

    expect(App::getLocale())->toBe('ar');
});

it('falls back to config locale when session locale is not in available list', function () {
    config([
        'app.locale' => 'en',
        'filament-panel-base.locale.available' => ['en'],
    ]);

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));
    session(['locale' => 'fr']);

    $middleware = new SetLocale;
    $middleware->handle($request, fn () => new \Illuminate\Http\Response);

    expect(App::getLocale())->toBe('en');
});

it('provides static getLocales method', function () {
    config(['filament-panel-base.locale.available' => ['en', 'ar']]);

    $locales = SetLocale::getLocales();

    expect($locales)->toBeArray()
        ->and($locales)->toHaveKey('en')
        ->and($locales)->toHaveKey('ar');
});

it('builds locale array from config when no provider is set', function () {
    config([
        'filament-panel-base.locale.provider' => null,
        'filament-panel-base.locale.available' => ['en', 'fr'],
    ]);

    $locales = SetLocale::getLocales();

    expect($locales)->toHaveCount(2)
        ->and($locales['en'])->toHaveKey('name')
        ->and($locales['en'])->toHaveKey('native')
        ->and($locales['en'])->toHaveKey('dir')
        ->and($locales['en'])->toHaveKey('flag');
});

// ─── EnsureUserApproved ──────────────────────────────────

it('redirects to login when not authenticated', function () {
    Route::get('/login', fn () => 'login')->name('login');

    Auth::shouldReceive('user')->andReturn(null);

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));

    $middleware = new EnsureUserApproved;
    $response = $middleware->handle($request, fn () => new \Illuminate\Http\Response);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('login');
});

it('allows non-moderable users through', function () {
    Route::get('/login', fn () => 'login')->name('login');

    // A user that does NOT implement HasModerationStatus
    $user = new class
    {
        public function isSuspended(): bool
        {
            return true;
        }
    };

    Auth::shouldReceive('user')->andReturn($user);

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));

    $middleware = new EnsureUserApproved;
    $response = $middleware->handle($request, fn () => new \Illuminate\Http\Response('OK'));

    expect($response->getStatusCode())->toBe(200);
});

it('blocks suspended users', function () {
    Route::get('/login', fn () => 'login')->name('login');

    $user = new class implements HasModerationStatus
    {
        public function isSuspended(): bool
        {
            return true;
        }

        public function isPending(): bool
        {
            return false;
        }
    };

    Auth::shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('logout')->once();

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));

    $middleware = new EnsureUserApproved;
    $response = $middleware->handle($request, fn () => new \Illuminate\Http\Response('OK'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('login');
});

it('redirects pending users to home', function () {
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/home', fn () => 'home')->name('home');

    $user = new class implements HasModerationStatus
    {
        public function isSuspended(): bool
        {
            return false;
        }

        public function isPending(): bool
        {
            return true;
        }
    };

    Auth::shouldReceive('user')->andReturn($user);

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));

    $middleware = new EnsureUserApproved;
    $response = $middleware->handle($request, fn () => new \Illuminate\Http\Response('OK'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('home');
});

it('allows approved users through', function () {
    $user = new class implements HasModerationStatus
    {
        public function isSuspended(): bool
        {
            return false;
        }

        public function isPending(): bool
        {
            return false;
        }
    };

    Auth::shouldReceive('user')->andReturn($user);

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));

    $middleware = new EnsureUserApproved;
    $response = $middleware->handle($request, fn () => new \Illuminate\Http\Response('OK'));

    expect($response->getStatusCode())->toBe(200);
});
