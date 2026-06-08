<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Support;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

class SessionExpiry
{
    /**
     * Resolve where a 419 (expired session) should send the user.
     *
     * Order of precedence:
     *   1. config('filament-panel-base.session_expiry.redirect_to') if set;
     *   2. the current Filament panel's own login URL (e.g. /admin/login);
     *   3. the app's named `login` route;
     *   4. the site root.
     */
    public static function redirectUrl(): string
    {
        $configured = config('filament-panel-base.session_expiry.redirect_to');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        try {
            if (function_exists('filament') && ($panel = Filament::getCurrentPanel()) !== null) {
                if ($panel->hasLogin()) {
                    return $panel->getLoginUrl();
                }
            }
        } catch (\Throwable) {
            // Filament not booted or no panel context — fall through.
        }

        return Route::has('login') ? route('login') : url('/');
    }
}
