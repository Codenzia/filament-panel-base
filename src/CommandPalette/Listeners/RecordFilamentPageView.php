<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette\Listeners;

use Codenzia\FilamentPanelBase\CommandPalette\Services\RecentViewRecorder;
use Codenzia\FilamentPanelBase\CommandPalette\Settings\CommandPaletteSettings;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Wired into Filament's `serving()` hook by bootCommandPaletteModule().
 * Inspects the current Livewire component and, if it's a Filament
 * resource record page (EditRecord / ViewRecord / similar), records
 * a recent view so the user can jump back to it from the command palette.
 *
 * The handler is invoked on every Filament-served request — cheap by
 * design: short-circuits when the command-palette module is disabled,
 * when the user isn't authenticated, or when the current page is not
 * a record page.
 */
class RecordFilamentPageView
{
    public function __construct(
        private RecentViewRecorder $recorder,
        private CommandPaletteSettings $settings,
    ) {}

    public function handle(): void
    {
        try {
            if (! $this->settings->enabled || ! $this->settings->track_recent_views) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        try {
            $panel = Filament::getCurrentPanel();
            $page = $this->detectRecordPage();
        } catch (\Throwable) {
            return;
        }

        if ($page === null) {
            return;
        }

        [$resourceClass, $record, $label, $url] = $page;

        $this->recorder->record(
            $resourceClass,
            $record,
            $url,
            $label,
            $panel?->getId(),
        );
    }

    /**
     * Walks the current request to see if Livewire/Filament is rendering
     * a resource record page. Returns [resourceClass, record, label, url]
     * or null if there's nothing to record.
     *
     * @return array{0: string, 1: Model, 2: string, 3: string}|null
     */
    private function detectRecordPage(): ?array
    {
        $route = request()?->route();

        if ($route === null) {
            return null;
        }

        $controller = $route->getController();

        if (! is_object($controller)) {
            return null;
        }

        // Filament resource pages extend BaseRecordPage and expose a
        // `getRecord()` method + `getResource()` static, the only two
        // ducks we need to feed the recorder.
        if (! method_exists($controller, 'getRecord') || ! method_exists($controller, 'getResource')) {
            return null;
        }

        try {
            $record = $controller->getRecord();
            $resourceClass = $controller::getResource();
        } catch (\Throwable) {
            return null;
        }

        if (! $record instanceof Model) {
            return null;
        }

        $label = $this->labelFor($record, $resourceClass);
        $url = (string) (request()?->fullUrl() ?? '');

        if ($url === '') {
            return null;
        }

        return [(string) $resourceClass, $record, $label, $url];
    }

    private function labelFor(Model $record, string $resourceClass): string
    {
        if (method_exists($resourceClass, 'getRecordTitle')) {
            try {
                $title = $resourceClass::getRecordTitle($record);

                if (is_string($title) && $title !== '') {
                    return $title;
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        $candidates = ['name', 'title', 'label', 'email'];

        foreach ($candidates as $field) {
            if (isset($record->{$field}) && is_string($record->{$field}) && $record->{$field} !== '') {
                return (string) $record->{$field};
            }
        }

        return class_basename($resourceClass).' #'.$record->getKey();
    }
}
