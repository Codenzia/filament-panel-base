<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Uses the default TestCase (otp_api.enabled defaults to false).

it('does not register the OTP API routes when the module is disabled', function (): void {
    expect(config('filament-panel-base.otp_api.enabled'))->toBeFalse();
    expect(Route::has('filament-panel-base.otp-api.otp.request'))->toBeFalse();
    expect(Route::has('filament-panel-base.otp-api.otp.verify'))->toBeFalse();
    expect(Route::has('filament-panel-base.otp-api.phone.register'))->toBeFalse();
});
