<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sessions\Concerns;

use Codenzia\FilamentPanelBase\Sessions\Settings\SessionManagementSettings;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;

/**
 * Adds a "Devices & Sessions" tab to the profile slide-over. The host
 * PanelProvider already mixes in HasProfileSlideOver; this trait piggybacks
 * by overriding getProfileFormTabs().
 *
 *     use HasProfileSlideOver, HasSessionManagementProfileTab;
 *
 *     protected function getProfileFormTabs(): array
 *     {
 *         return [
 *             ...parent::getProfileFormTabs(),
 *             $this->getSessionManagementProfileTab(),
 *         ];
 *     }
 *
 * The tab renders the DeviceSessionList Livewire component. Sessions are
 * pulled from Laravel's `sessions` table on every render; no caching, but
 * the list is short enough that this is fine.
 */
trait HasSessionManagementProfileTab
{
    protected function getSessionManagementProfileTab(): Tab
    {
        return Tab::make(__('filament-panel-base::sessions.tab_label'))
            ->icon('heroicon-o-computer-desktop')
            ->visible(fn (): bool => $this->sessionManagementEnabled())
            ->components([
                SchemaView::make('filament-panel-base::filament.sessions.profile-tab')
                    ->columnSpanFull(),
            ]);
    }

    protected function sessionManagementEnabled(): bool
    {
        try {
            return app(SessionManagementSettings::class)->enabled;
        } catch (\Throwable) {
            return false;
        }
    }
}
