<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette\Contributors;

use Codenzia\FilamentPanelBase\CommandPalette\Contracts\CommandPaletteContributor;
use Codenzia\FilamentPanelBase\CommandPalette\Data\CommandPaletteAction;
use Filament\Facades\Filament;

/**
 * Walks the current panel's resources + pages and exposes each as a
 * "Go to ..." entry. This is the default contributor — registered
 * automatically by bootCommandPaletteModule() so the palette is useful
 * out of the box with zero host configuration.
 */
class FilamentNavigationContributor implements CommandPaletteContributor
{
    public function actions(?string $query = null): iterable
    {
        $panel = $this->currentPanel();

        if ($panel === null) {
            return [];
        }

        $panelId = $panel->getId();
        $actions = [];

        foreach ($panel->getResources() as $resource) {
            $url = $this->safeRoute(fn (): string => $resource::getUrl('index'));

            if ($url === null) {
                continue;
            }

            $actions[] = new CommandPaletteAction(
                id: "resource:{$panelId}:{$resource}",
                label: __('Go to :name', ['name' => $this->resourceLabel($resource)]),
                url: $url,
                description: __('filament-panel-base::command-palette.go_to_resource'),
                icon: $this->safeCall(fn () => $resource::getNavigationIcon()) ?: 'heroicon-o-folder',
                group: __('filament-panel-base::command-palette.group_navigation'),
                keywords: ['list', 'index', 'browse'],
            );
        }

        foreach ($panel->getPages() as $page) {
            $url = $this->safeRoute(fn (): string => $page::getUrl());

            if ($url === null) {
                continue;
            }

            $actions[] = new CommandPaletteAction(
                id: "page:{$panelId}:{$page}",
                label: $this->safeCall(fn () => $page::getNavigationLabel())
                    ?? class_basename($page),
                url: $url,
                description: __('filament-panel-base::command-palette.go_to_page'),
                icon: $this->safeCall(fn () => $page::getNavigationIcon()) ?: 'heroicon-o-document',
                group: __('filament-panel-base::command-palette.group_navigation'),
                keywords: ['page', 'open'],
            );
        }

        return $actions;
    }

    private function currentPanel(): ?\Filament\Panel
    {
        if (! class_exists(Filament::class)) {
            return null;
        }

        try {
            return Filament::getCurrentPanel();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resourceLabel(string $resource): string
    {
        return (string) ($this->safeCall(fn () => $resource::getNavigationLabel())
            ?? $this->safeCall(fn () => $resource::getPluralModelLabel())
            ?? class_basename($resource));
    }

    private function safeRoute(callable $fn): ?string
    {
        try {
            $value = $fn();

            return is_string($value) ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeCall(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }
}
