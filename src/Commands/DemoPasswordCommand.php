<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Commands;

use Codenzia\FilamentPanelBase\Models\DemoSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * View, regenerate, or set the /demo page password from the CLI.
 *
 * Persistence:
 *   - Writes to the `demo_settings` singleton row (encrypted cast).
 *   - Reads fall back to the configured env var (default APP_DEMO_PAGE_PWD)
 *     when the DB row is missing/empty — same order as DemoPage::expectedPassword().
 */
class DemoPasswordCommand extends Command
{
    protected $signature = 'demo:password
        {--regenerate : Generate a fresh 16-character random password and save it}
        {--set= : Set the password to a specific value and save it}';

    protected $description = 'View, regenerate, or set the /demo page password.';

    public function handle(): int
    {
        if ($this->option('regenerate')) {
            return $this->save(Str::random(16), regenerated: true);
        }

        $set = $this->option('set');
        if ($set !== null && $set !== '') {
            return $this->save((string) $set, regenerated: false);
        }

        $current = $this->currentPassword();
        if ($current === null || $current === '') {
            $this->warn('No demo password is configured.');
            $this->line('Run: php artisan demo:password --regenerate');

            return self::SUCCESS;
        }

        $this->line($current);
        $this->newLine();
        $this->comment('Source: '.$this->source());

        return self::SUCCESS;
    }

    private function save(string $password, bool $regenerated): int
    {
        if (! Schema::hasTable('demo_settings')) {
            $this->error('The demo_settings table does not exist.');
            $this->line('Publish + run the migration first:');
            $this->line('  php artisan vendor:publish --tag=filament-panel-base-demo-migrations');
            $this->line('  php artisan migrate');

            return self::FAILURE;
        }

        $row = DemoSetting::current();
        $row->password = $password;
        $row->rotated_at = now();
        $row->save();

        $this->info($regenerated ? 'New demo password generated and saved.' : 'Demo password updated.');
        $this->line($password);

        return self::SUCCESS;
    }

    private function currentPassword(): ?string
    {
        if (Schema::hasTable('demo_settings')) {
            $row = DemoSetting::current();
            if (is_string($row->password) && $row->password !== '') {
                return $row->password;
            }
        }

        $value = config('filament-panel-base.demo.password');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function source(): string
    {
        if (Schema::hasTable('demo_settings')) {
            $row = DemoSetting::current();
            if (is_string($row->password) && $row->password !== '') {
                return 'demo_settings table (DB)';
            }
        }

        return 'APP_DEMO_PAGE_PWD (env)';
    }
}
