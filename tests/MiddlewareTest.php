<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Codenzia\FilamentPanelBase\Middleware\EnsureUserApproved;
use Codenzia\FilamentPanelBase\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;

// ─── SetLocale ───────────────────────────────────────────

it('sets locale from session', function () {
    config(['filament-panel-base.locale.available' => ['en', 'ar']]);

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));
    session(['locale' => 'ar']);

    $middleware = new SetLocale;
    $middleware->handle($request, fn () => new Response);

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
    $middleware->handle($request, fn () => new Response);

    expect(App::getLocale())->toBe('en');
});

it('provides static getLocales method', function () {
    config(['filament-panel-base.locale.available' => ['en', 'ar']]);

    $locales = SetLocale::getLocales();

    expect($locales)->toBeArray()
        ->and($locales)->toHaveKey('en')
        ->and($locales)->toHaveKey('ar');
});

it('propagates the chosen locale to Carbon and the Number helper', function () {
    config(['filament-panel-base.locale.available' => ['en', 'ar']]);

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));
    session(['locale' => 'ar']);

    (new SetLocale)->handle($request, fn () => new Response);

    expect(App::getLocale())->toBe('ar')
        ->and(Carbon::getLocale())->toBe('ar')
        ->and(CarbonImmutable::getLocale())->toBe('ar');

    if (class_exists(Number::class)) {
        expect(Number::defaultLocale())->toBe('ar');
    }
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

it('marks known RTL locales as rtl in the config-fallback payload', function () {
    config(['filament-panel-base.locale.available' => ['en', 'ar', 'he', 'fa', 'ur', 'fr']]);

    $locales = SetLocale::getLocales();

    expect($locales['en']['dir'])->toBe('ltr')
        ->and($locales['ar']['dir'])->toBe('rtl')
        ->and($locales['he']['dir'])->toBe('rtl')
        ->and($locales['fa']['dir'])->toBe('rtl')
        ->and($locales['ur']['dir'])->toBe('rtl')
        ->and($locales['fr']['dir'])->toBe('ltr');
});

it('flips Filament panels layout direction to rtl when an RTL locale is active', function () {
    config(['filament-panel-base.locale.available' => ['en', 'ar']]);

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));
    session(['locale' => 'ar']);

    (new SetLocale)->handle($request, fn () => new Response);

    expect(__('filament-panels::layout.direction'))->toBe('rtl');
});

// ─── EnsureUserApproved ──────────────────────────────────

it('redirects to login when not authenticated', function () {
    Route::get('/login', fn () => 'login')->name('login');

    Auth::shouldReceive('user')->andReturn(null);

    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));

    $middleware = new EnsureUserApproved;
    $response = $middleware->handle($request, fn () => new Response);

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
    $response = $middleware->handle($request, fn () => new Response('OK'));

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
    $response = $middleware->handle($request, fn () => new Response('OK'));

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
    $response = $middleware->handle($request, fn () => new Response('OK'));

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
    $response = $middleware->handle($request, fn () => new Response('OK'));

    expect($response->getStatusCode())->toBe(200);
});
