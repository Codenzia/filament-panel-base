<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Standalone verification-link handler. Replaces Laravel's default
 * controller so the module owns the redirect path (configurable via
 * `filament-panel-base.auth.routes.verified_redirect`).
 */
class VerifyEmailController
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($this->redirectPath().'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended($this->redirectPath().'?verified=1');
    }

    private function redirectPath(): string
    {
        $route = config('filament-panel-base.auth.routes.verified_redirect');

        if (is_string($route) && $route !== '' && \Illuminate\Support\Facades\Route::has($route)) {
            return route($route);
        }

        return $route ?? url('/');
    }
}
