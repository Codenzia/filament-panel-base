<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette\Services;

use Codenzia\FilamentPanelBase\CommandPalette\Models\RecentView;
use Codenzia\FilamentPanelBase\CommandPalette\Settings\CommandPaletteSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Persists "the current user just opened this record" rows so they appear
 * under "Recent" in the palette. Designed to be called from a Filament
 * record-page lifecycle hook (see RecentViewServingHook) — every other
 * code path should treat it as fire-and-forget.
 *
 * Idempotent on the (user, panel, resource, record) tuple: existing rows
 * just have their viewed_at bumped. After each write, prunes anything
 * past `recent_view_limit` for that (user, panel) so the table stays small.
 */
class RecentViewRecorder
{
    public function __construct(private CommandPaletteSettings $settings) {}

    public function record(string $resourceClass, Model $record, string $url, string $label, ?string $panelId): void
    {
        if (! $this->settings->enabled || ! $this->settings->track_recent_views) {
            return;
        }

        if (! Schema::hasTable('command_palette_recent_views')) {
            return;
        }

        $user = Auth::user();

        if ($user === null) {
            return;
        }

        try {
            RecentView::updateOrCreate(
                [
                    'user_id' => $user->getAuthIdentifier(),
                    'panel' => $panelId,
                    'resource_class' => $resourceClass,
                    'record_id' => (string) $record->getKey(),
                ],
                [
                    'label' => mb_substr($label, 0, 255),
                    'url' => mb_substr($url, 0, 2048),
                    'viewed_at' => now(),
                ],
            );

            $this->pruneFor($user->getAuthIdentifier(), $panelId);
        } catch (\Throwable) {
            // Recording is best-effort.
        }
    }

    private function pruneFor(int|string $userId, ?string $panelId): void
    {
        $limit = max(1, $this->settings->recent_view_limit);

        $excessIds = RecentView::query()
            ->where('user_id', $userId)
            ->where('panel', $panelId)
            ->orderByDesc('viewed_at')
            ->skip($limit)
            ->take(100)
            ->pluck('id');

        if ($excessIds->isNotEmpty()) {
            RecentView::query()->whereIn('id', $excessIds)->delete();
        }
    }
}
