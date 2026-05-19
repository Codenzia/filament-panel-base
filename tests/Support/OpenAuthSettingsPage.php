<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Tests\Support;

use Codenzia\FilamentPanelBase\Auth\Filament\Pages\ManageAuthenticationSettings;

/**
 * Test-only subclass that opens the page's authorisation. Mirrors the
 * host-side pattern documented in the README — production hosts subclass
 * and apply either filament-shield or a Gate ability; here we just return
 * `true` so the Livewire mount/save flow can be driven end-to-end.
 */
class OpenAuthSettingsPage extends ManageAuthenticationSettings
{
    public static function canAccess(): bool
    {
        return true;
    }
}
