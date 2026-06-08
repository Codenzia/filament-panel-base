<?php

use Codenzia\FilamentPanelBase\Support\SessionExpiry;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('enables session-expiry handling by default', function () {
    expect(config('filament-panel-base.session_expiry.enabled'))->toBeTrue()
        ->and(config('filament-panel-base.session_expiry.redirect_to'))->toBeNull();
});

describe('SessionExpiry::redirectUrl', function () {
    it('honours an explicit redirect_to config override', function () {
        config(['filament-panel-base.session_expiry.redirect_to' => '/custom-login']);

        expect(SessionExpiry::redirectUrl())->toBe('/custom-login');
    });

    it('falls back to the named login route', function () {
        config(['filament-panel-base.session_expiry.redirect_to' => null]);
        Route::get('/login', fn () => 'login')->name('login');

        expect(SessionExpiry::redirectUrl())->toBe(route('login'));
    });
});

describe('419 exception handling', function () {
    it('redirects full-page CSRF failures to the login screen', function () {
        config(['filament-panel-base.session_expiry.redirect_to' => '/login']);

        $request = Request::create('/some-page', 'POST');
        $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException);

        expect($response->getStatusCode())->toBe(302)
            ->and($response->headers->get('Location'))->toContain('/login');
    });

    it('leaves Livewire/AJAX 419s untouched for the client-side hook', function () {
        config(['filament-panel-base.session_expiry.redirect_to' => '/login']);

        $request = Request::create('/livewire/update', 'POST');
        $request->headers->set('X-Livewire', 'true');

        $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException);

        // Not a redirect — left as a 419 so the injected Livewire hook handles it.
        expect($response->getStatusCode())->toBe(419);
    });

    it('ignores HttpExceptions that are not 419', function () {
        $request = Request::create('/missing', 'GET');
        $response = app(ExceptionHandler::class)->render($request, new NotFoundHttpException);

        expect($response->getStatusCode())->toBe(404);
    });

    it('ignores a 419 not caused by a token mismatch', function () {
        $request = Request::create('/some-page', 'POST');
        $response = app(ExceptionHandler::class)->render($request, new HttpException(419));

        expect($response->getStatusCode())->toBe(419)
            ->and($response->headers->get('Location'))->toBeNull();
    });
});

it('renders the Livewire 419 interceptor partial', function () {
    $html = view('filament-panel-base::session-expiry.script', [
        'redirectUrl' => '/login',
    ])->render();

    expect($html)->toContain('status === 419')
        ->toContain('redirect_after_login')
        ->toContain('window.location.href');
});

it('injects the interceptor into the panel body via the BODY_END render hook', function () {
    $html = (string) FilamentView::renderHook(PanelsRenderHook::BODY_END);

    expect($html)->toContain('status === 419')
        ->toContain('redirect_after_login');
});

describe('kill switch (PANEL_SESSION_EXPIRY=false)', function () {
    // The module gates itself at boot, so the flag must be in place before the
    // provider boots. Setting the env the config reads and reloading the app
    // re-evaluates the gate. (Same env-manipulation pattern as the demo tests.)
    beforeEach(function () {
        Env::getRepository()->set('PANEL_SESSION_EXPIRY', 'false');
        $this->reloadApplication();
    });

    afterEach(function () {
        Env::getRepository()->clear('PANEL_SESSION_EXPIRY');
    });

    it('does not inject the interceptor when disabled', function () {
        $html = (string) FilamentView::renderHook(PanelsRenderHook::BODY_END);

        expect($html)->not->toContain('status === 419');
    });

    it('does not redirect 419s when disabled', function () {
        $request = Request::create('/some-page', 'POST');
        $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException);

        expect($response->getStatusCode())->toBe(419);
    });
});
