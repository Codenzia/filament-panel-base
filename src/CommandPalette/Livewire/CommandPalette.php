<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette\Livewire;

use Codenzia\FilamentPanelBase\CommandPalette\CommandPaletteRegistry;
use Codenzia\FilamentPanelBase\CommandPalette\Data\CommandPaletteAction;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Headless Livewire body of the Cmd-K modal. The trigger is Alpine.js
 * (see commandPaletteModal Blade view) — opening it dispatches a
 * `cmd-palette-opened` browser event that Livewire reacts to.
 *
 * Query string is debounced client-side via `wire:model.live.debounce`,
 * not server-side, so each keystroke produces a single round-trip.
 */
class CommandPalette extends Component
{
    public string $query = '';

    /**
     * Per-render cached list of every action the registry can produce.
     * Cheap operation since contributors are bounded — caching here just
     * keeps the score-and-sort below symmetric with the unfiltered case.
     *
     * @return array<int, CommandPaletteAction>
     */
    #[Computed]
    public function actions(): array
    {
        /** @var CommandPaletteRegistry $registry */
        $registry = app(CommandPaletteRegistry::class);

        $all = $registry->collect($this->query ?: null);

        return $this->scoreAndSort($all);
    }

    /**
     * @return array<string, array<int, CommandPaletteAction>>
     */
    public function getGroupedActionsProperty(): array
    {
        $groups = [];

        foreach ($this->actions as $action) {
            $key = $action->group ?? 'General';
            $groups[$key] ??= [];
            $groups[$key][] = $action;
        }

        return $groups;
    }

    /**
     * Cheap substring + leading-token scoring. Actions whose label STARTS
     * with the query rank highest; substring matches anywhere rank lower.
     * Empty queries skip scoring entirely.
     *
     * @param  array<int, CommandPaletteAction>  $actions
     * @return array<int, CommandPaletteAction>
     */
    private function scoreAndSort(array $actions): array
    {
        if ($this->query === '') {
            return $actions;
        }

        $needle = mb_strtolower(trim($this->query));

        $scored = [];

        foreach ($actions as $action) {
            $haystack = $action->searchHaystack();
            $labelLower = mb_strtolower($action->label);

            $score = 0;

            if (str_starts_with($labelLower, $needle)) {
                $score = 100;
            } elseif (str_contains($labelLower, $needle)) {
                $score = 60;
            } elseif (str_contains($haystack, $needle)) {
                $score = 30;
            }

            if ($score > 0) {
                $scored[] = ['score' => $score, 'action' => $action];
            }
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(static fn ($entry) => $entry['action'], $scored);
    }

    public function render(): View
    {
        return view('filament-panel-base::livewire.command-palette.body', [
            'groups' => $this->getGroupedActionsProperty(),
        ]);
    }
}
