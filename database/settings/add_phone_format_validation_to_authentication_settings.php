<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('auth.phone_format_validation')) {
            $this->migrator->add('auth.phone_format_validation', true);
        }
    }

    public function down(): void
    {
        if ($this->migrator->exists('auth.phone_format_validation')) {
            $this->migrator->delete('auth.phone_format_validation');
        }
    }
};
