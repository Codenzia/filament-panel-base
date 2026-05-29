<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('command_palette.enabled', true);
        $this->migrator->add('command_palette.track_recent_views', true);
        $this->migrator->add('command_palette.recent_view_limit', 10);
        $this->migrator->add('command_palette.hotkey_label', 'Ctrl+K');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('command_palette.enabled');
        $this->migrator->deleteIfExists('command_palette.track_recent_views');
        $this->migrator->deleteIfExists('command_palette.recent_view_limit');
        $this->migrator->deleteIfExists('command_palette.hotkey_label');
    }
};
