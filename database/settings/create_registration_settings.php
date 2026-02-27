<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('registration.registration_mode')) {
            $this->migrator->add('registration.registration_mode', 'open');
        }

        if (! $this->migrator->exists('registration.require_email_verification')) {
            $this->migrator->add('registration.require_email_verification', true);
        }
    }
};
