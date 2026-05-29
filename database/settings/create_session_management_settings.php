<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('session_management.enabled', true);
        $this->migrator->add('session_management.notify_on_new_device', true);
        $this->migrator->add('session_management.idle_threshold_minutes', 30);
        $this->migrator->add('session_management.allow_logout_other_devices', true);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('session_management.enabled');
        $this->migrator->deleteIfExists('session_management.notify_on_new_device');
        $this->migrator->deleteIfExists('session_management.idle_threshold_minutes');
        $this->migrator->deleteIfExists('session_management.allow_logout_other_devices');
    }
};
