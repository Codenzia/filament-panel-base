<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Filament\Pages;

use Filament\Pages\SimplePage;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Filament-chrome wrapper around the TwoFactorChallenge Livewire component.
 * Mount on a panel that wants the challenge rendered inside its own
 * auth-style card via:
 *
 *   FilamentPanelBasePlugin::make()->withFilamentTwoFactorChallengePage();
 *
 * Hosts using the public Livewire route (`/two-factor-challenge`) don't
 * need this — it's purely for panels that prefer the boxed Filament card
 * with the panel's branding (logo, colours, locale switcher).
 *
 * Routing note: this class extends `SimplePage`, which Filament designs
 * for auth-style pages registered via slot methods (`->login()`,
 * `->register()`) — NOT via `$panel->pages([...])`. SimplePage skips the
 * `HasRoutes` trait that powers Page::registerRoutes(), so the plugin
 * registers the URL through `$panel->routes(...)` instead. See
 * FilamentPanelBasePlugin::apply().
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
