<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette\Contracts;

use Codenzia\FilamentPanelBase\CommandPalette\Data\CommandPaletteAction;

/**
 * Hosts and consumer plugins implement this and register with the
 * CommandPaletteRegistry to push extra entries into the modal. The
 * registry calls `actions($query)` on every keystroke — implementations
 * should be cheap and return only what's relevant to the current query.
 *
 * Pass `null` to indicate "default / no filter applied yet"; return your
 * full list (capped at a reasonable size, e.g. 25 rows).
 */
interface CommandPaletteContributor
{
    /**
     * @return iterable<CommandPaletteAction>
     */
    public function actions(?string $query = null): iterable;
}
