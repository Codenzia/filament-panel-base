<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette\Contributors;

use Codenzia\FilamentPanelBase\CommandPalette\Contracts\CommandPaletteContributor;
use Codenzia\FilamentPanelBase\CommandPalette\Data\CommandPaletteAction;
use Codenzia\FilamentPanelBase\CommandPalette\Models\RecentView;
use Codenzia\FilamentPanelBase\CommandPalette\Settings\CommandPaletteSettings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Surfaces the current user's last N viewed records (per panel) under a
 * "Recent" group at the top of the palette. Pulls from
 * `command_palette_recent_views`, written to by RecentViewRecorder when
 * Filament serves a record-edit page.
 */
class RecentViewsContributor implements CommandPaletteContributor
{
    public function __construct(private CommandPaletteSettings $settings) {}

    public function actions(?string $query = null): iterable
    {
        if (! $this->settings->enabled || ! $this->settings->track_recent_views) {
            return [];
        }

        if (! Schema::hasTable('command_palette_recent_views')) {
            return [];
        }

        $user = Auth::user();

        if ($user === null) {
            return [];
        }

        $panelId = $this->currentPanelId();

        try {
            $rows = RecentView::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('panel', $panelId)
                ->orderByDesc('viewed_at')
                ->limit($this->settings->recent_view_limit)
                ->get();
        } catch (\Throwable) {
            return [];
        }

        $group = __('filament-panel-base::command-palette.group_recent');

        return $rows->map(fn (RecentView $row): CommandPaletteAction => new CommandPaletteAction(
            id: "recent:{$row->id}",
            label: $row->label,
            url: $row->url,
            description: $row->viewed_at?->diffForHumans(),
            icon: 'heroicon-o-clock',
            group: $group,
        ))->all();
    }

    private function currentPanelId(): ?string
    {
        if (! class_exists(Filament::class)) {
            return null;
        }

        try {
            return Filament::getCurrentPanel()?->getId();
        } catch (\Throwable) {
            return null;
        }
    }
}
