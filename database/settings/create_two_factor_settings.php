<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('two_factor.enabled', true);
        $this->migrator->add('two_factor.issuer', null);
        $this->migrator->add('two_factor.recovery_code_count', 8);
        $this->migrator->add('two_factor.digits', 6);
        $this->migrator->add('two_factor.period', 30);
        $this->migrator->add('two_factor.window', 1);
        $this->migrator->add('two_factor.require_for_roles', []);
        $this->migrator->add('two_factor.remember_device', true);
        $this->migrator->add('two_factor.remember_device_days', 30);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('two_factor.enabled');
        $this->migrator->deleteIfExists('two_factor.issuer');
        $this->migrator->deleteIfExists('two_factor.recovery_code_count');
        $this->migrator->deleteIfExists('two_factor.digits');
        $this->migrator->deleteIfExists('two_factor.period');
        $this->migrator->deleteIfExists('two_factor.window');
        $this->migrator->deleteIfExists('two_factor.require_for_roles');
        $this->migrator->deleteIfExists('two_factor.remember_device');
        $this->migrator->deleteIfExists('two_factor.remember_device_days');
    }
};
