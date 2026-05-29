<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette;

use Codenzia\FilamentPanelBase\CommandPalette\Contracts\CommandPaletteContributor;
use Codenzia\FilamentPanelBase\CommandPalette\Data\CommandPaletteAction;

/**
 * Application-singleton holding every contributor pushed into the palette.
 * Contributors are anything implementing CommandPaletteContributor, or a
 * raw array/closure that produces actions directly.
 *
 * The Livewire component calls `collect($query)` on every keystroke; the
 * registry walks contributors in registration order, flattens their
 * results, dedupes by `id`, and returns up to `$limit` entries.
 */
class CommandPaletteRegistry
{
    /** @var array<int, CommandPaletteContributor|callable|array<int, CommandPaletteAction>> */
    private array $contributors = [];

    public function register(CommandPaletteContributor|callable|array $contributor): static
    {
        $this->contributors[] = $contributor;

        return $this;
    }

    /**
     * @return array<int, CommandPaletteAction>
     */
    public function collect(?string $query = null, int $limit = 50): array
    {
        $out = [];
        $seen = [];

        foreach ($this->contributors as $contributor) {
            try {
                $actions = $this->resolve($contributor, $query);
            } catch (\Throwable) {
                continue;
            }

            foreach ($actions as $action) {
                if (! $action instanceof CommandPaletteAction) {
                    continue;
                }

                if (isset($seen[$action->id])) {
                    continue;
                }

                $seen[$action->id] = true;
                $out[] = $action;

                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * @return iterable<CommandPaletteAction>
     */
    private function resolve(mixed $contributor, ?string $query): iterable
    {
        if ($contributor instanceof CommandPaletteContributor) {
            return $contributor->actions($query);
        }

        if (is_callable($contributor)) {
            return $contributor($query);
        }

        if (is_array($contributor)) {
            return $contributor;
        }

        return [];
    }
}
