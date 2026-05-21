<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Filament\Pages;

use Filament\Pages\SimplePage;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Adapter that mounts the module's Login Livewire component inside a
 * Filament panel's auth chrome. Opt-in via:
 *
 *   ->withAuthentication(fn ($auth) => $auth->filamentPanelPages(login: true))
 *
 * The class extends SimplePage so it inherits Filament's centered card
 * layout. All authentication logic lives in the embedded Livewire
 * component — this class is purely a mounting point.
 */
class Login extends SimplePage
{
    protected string $view = 'filament-panel-base::filament.auth.login';

    public function getHeading(): string|Htmlable
    {
        return __('filament-panel-base::auth.login_title');
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/login';
    }
}
