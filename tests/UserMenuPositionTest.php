<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Providers\BasePanelProvider;
use Filament\Enums\UserMenuPosition;
use Filament\Panel;

/**
 * Minimal concrete provider exposing configureSharedSettings() so the
 * userMenuPosition() wiring can be asserted against a real Panel instance
 * without booting a full Filament panel/tenant.
 */
class UserMenuPositionTestProvider extends BasePanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $this->configureSharedSettings($panel);
    }
}

it('leaves the user menu position to Filament\'s own default when unset', function (): void {
    $panel = (new UserMenuPositionTestProvider(app()))
        ->panel(Panel::make()->id('user-menu-default-test'));

    // Filament's own default: Topbar when the panel has one (the default).
    expect($panel->getUserMenuPosition())->toBe(UserMenuPosition::Topbar);
});

it('pins the user menu to the sidebar via the enum', function (): void {
    $panel = (new UserMenuPositionTestProvider(app()))
        ->userMenuPosition(UserMenuPosition::Sidebar)
        ->panel(Panel::make()->id('user-menu-enum-sidebar-test'));

    expect($panel->getUserMenuPosition())->toBe(UserMenuPosition::Sidebar);
});

it('accepts a plain string for the position, case-insensitively', function (): void {
    $panel = (new UserMenuPositionTestProvider(app()))
        ->userMenuPosition('SIDEBAR')
        ->panel(Panel::make()->id('user-menu-string-sidebar-test'));

    expect($panel->getUserMenuPosition())->toBe(UserMenuPosition::Sidebar);
});

it('accepts the topbar string explicitly, overriding what would otherwise default to sidebar', function (): void {
    $panel = (new UserMenuPositionTestProvider(app()))
        ->userMenuPosition('topbar')
        ->panel(Panel::make()->id('user-menu-string-topbar-test')->topbar(false));

    expect($panel->getUserMenuPosition())->toBe(UserMenuPosition::Topbar);
});

it('rejects an invalid position string', function (): void {
    (new UserMenuPositionTestProvider(app()))->userMenuPosition('nowhere');
})->throws(InvalidArgumentException::class, "Invalid user menu position [nowhere]. Expected 'topbar' or 'sidebar'.");
