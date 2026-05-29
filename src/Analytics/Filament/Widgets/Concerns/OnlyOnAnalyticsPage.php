<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns;

/**
 * Hide the widget everywhere except the analytics page. Filament checks
 * `canView()` at the point a Page decides which widgets to mount, so this
 * keeps the widgets off the default `/admin` dashboard while still letting
 * the AnalyticsPage's `getWidgets()` mount them.
 *
 * Subclass and override `canView()` to opt back into the dashboard.
 */
trait OnlyOnAnalyticsPage
{
    public static function canView(): bool
    {
        $routeName = request()->route()?->getName() ?? '';

        // Direct page render — only mount on the analytics page so the
        // default /admin dashboard stays uncluttered.
        if (str_ends_with($routeName, '.pages.analytics')) {
            return true;
        }

        // Livewire updates (polling, filter changes, post-navigation
        // tail requests) get unconditional allow. A widget only receives
        // a Livewire update if it was already mounted, and Livewire's
        // signed snapshot prevents arbitrary widget instantiation — so
        // there's no privilege-escalation risk here. Without this allow,
        // post-navigation in-flight polling requests 403 and surface as
        // a Livewire failure modal in the browser.
        if (str_contains($routeName, '.livewire.')) {
            return true;
        }

        return false;
    }
}
