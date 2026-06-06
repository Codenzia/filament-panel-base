<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('auth.allowed_email_domains')) {
            $this->migrator->add('auth.allowed_email_domains', []);
        }
    }

    public function down(): void
    {
        if ($this->migrator->exists('auth.allowed_email_domains')) {
            $this->migrator->delete('auth.allowed_email_domains');
        }
    }
};
