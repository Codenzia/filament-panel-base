<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Filament\Pages;

use Filament\Pages\SimplePage;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Adapter that mounts the module's Register Livewire component inside a
 * Filament panel's auth chrome.
 */
class Register extends SimplePage
{
    protected string $view = 'filament-panel-base::filament.auth.register';

    public function getHeading(): string|Htmlable
    {
        return __('filament-panel-base::auth.register_title');
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/register';
    }
}
