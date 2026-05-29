<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette\Settings;

use Codenzia\FilamentPanelBase\CommandPalette\CommandPalettePlugin;
use Spatie\LaravelSettings\Settings;

/**
 * Runtime-toggleable command-palette settings. Resolution order:
 * fluent API (CommandPalettePlugin) -> these settings -> hard-coded defaults.
 *
 * @see CommandPalettePlugin
 */
class CommandPaletteSettings extends Settings
{
    public bool $enabled = true;

    /**
     * Whether to auto-record visited resource records into the
     * `command_palette_recent_views` table so they show up in the modal.
     */
    public bool $track_recent_views = true;

    /** Max recent items kept per (user, panel) tuple. */
    public int $recent_view_limit = 10;

    /** Default keyboard hotkey hint shown in the modal trigger. */
    public string $hotkey_label = 'Ctrl+K';

    public static function group(): string
    {
        return 'command_palette';
    }
}
