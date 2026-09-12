<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Http\Controllers\Api\OtpController;
use Codenzia\FilamentPanelBase\Auth\Http\Controllers\Api\PhoneController;
use Illuminate\Support\Facades\Route;

/**
 * Headless OTP REST endpoints. Registered only when
 * `filament-panel-base.otp_api.enabled` is true. Prefix and middleware are
 * config-overridable; per-endpoint per-IP throttles are applied here.
 */
$throttle = static fn (string $key, string $default): string => 'throttle:'.(string) config(
    "filament-panel-base.otp_api.throttle.{$key}",
    $default,
);

Route::post('otp/request', [OtpController::class, 'request'])
    ->middleware($throttle('request', '10,60'))
    ->name('otp.request');

Route::post('otp/verify', [OtpController::class, 'verify'])
    ->middleware($throttle('verify', '20,60'))
    ->name('otp.verify');

Route::post('phone/register', [PhoneController::class, 'register'])
    ->middleware($throttle('register', '10,60'))
    ->name('phone.register');
