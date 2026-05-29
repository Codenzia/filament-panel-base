<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Filament\Pages;

use Filament\Pages\SimplePage;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Filament-chrome wrapper around the TwoFactorChallenge Livewire component.
 * Mount on a panel that owns its own auth chrome via:
 *
 *   $panel->pages([TwoFactorChallengePage::class]);
 *
 * Hosts using the public Livewire route (`/two-factor-challenge`) don't
 * need this — it's purely for panels that prefer the boxed Filament card.
 */
class TwoFactorChallengePage extends SimplePage
{
    protected string $view = 'filament-panel-base::filament.two-factor.challenge';

    public function getHeading(): string|Htmlable
    {
        return __('filament-panel-base::two-factor.challenge_title');
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/two-factor-challenge';
    }
}
