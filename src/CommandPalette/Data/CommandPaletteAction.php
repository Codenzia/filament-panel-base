<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette\Data;

/**
 * Read-only DTO describing a single command-palette entry. A contributor
 * returns an array of these from its `actions()` method. The Livewire
 * search component scores them against the query string and groups them
 * under the action's `group` label in the UI.
 *
 * Keep payloads small — every keystroke re-runs the scoring loop.
 */
readonly class CommandPaletteAction
{
    /**
     * @param  array<int, string>  $keywords  Synonyms boosted during scoring.
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $url,
        public ?string $description = null,
        public ?string $icon = null,
        public ?string $group = null,
        public array $keywords = [],
        public ?string $shortcut = null,
    ) {}

    /** Lowercase token used for fuzzy substring matching. */
    public function searchHaystack(): string
    {
        return strtolower(implode(' ', array_filter([
            $this->label,
            $this->description,
            $this->group,
            implode(' ', $this->keywords),
        ])));
    }
}
