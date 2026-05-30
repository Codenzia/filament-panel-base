<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Http\Controllers\LogoutController;
use Codenzia\FilamentPanelBase\Auth\Http\Controllers\OAuthController;
use Codenzia\FilamentPanelBase\Auth\Http\Controllers\VerifyEmailController;
use Codenzia\FilamentPanelBase\Auth\Http\Middleware\ThrottleAuth;
use Codenzia\FilamentPanelBase\Auth\Livewire\ForgotPassword;
use Codenzia\FilamentPanelBase\Auth\Livewire\Login;
use Codenzia\FilamentPanelBase\Auth\Livewire\Register;
use Codenzia\FilamentPanelBase\Auth\Livewire\ResetPassword;
use Codenzia\FilamentPanelBase\Auth\Livewire\VerifyEmailNotice;
use Codenzia\FilamentPanelBase\Auth\Livewire\VerifyOtp;
use Codenzia\FilamentPanelBase\TwoFactor\Livewire\TwoFactorChallenge;
use Illuminate\Support\Facades\Route;

/**
 * Auth routes shipped by codenzia/filament-panel-base. Disable with
 * `config('filament-panel-base.auth.routes.enabled') = false` and wire
 * your own routes pointing at the same Livewire components / controllers.
 *
 * Brute-force protection note:
 * The Livewire form submissions on these pages POST to /livewire/update,
 * which bypasses route-level middleware. Credential-, OTP-, and token-level
 * throttling for those flows lives inside the Livewire components themselves
 * via Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts. The
 * ThrottleAuth middleware is reserved for native HTTP routes (OAuth GETs)
 * where every hit has a real backend cost.
 */
Route::middleware(['guest'])->group(function (): void {
    Route::get('/register', Register::class)->name('register');
    Route::get('/login', Login::class)->name('login');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware(['auth'])->group(function (): void {
    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', VerifyEmailNotice::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware('signed')
        ->name('verification.verify');

    Route::get('/verify-otp', VerifyOtp::class)->name('verification.otp');
});

// 2FA challenge: registered outside the 'auth' group because the user has
// NOT logged in at this point — only credentials passed. Pending state
// lives in the session via TwoFactorChallengeSession.
Route::middleware(['web'])->group(function (): void {
    Route::get('/two-factor-challenge', TwoFactorChallenge::class)
        ->name('two-factor.challenge');
});

Route::middleware([ThrottleAuth::class])->group(function (): void {
    Route::get('/oauth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/oauth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
});
