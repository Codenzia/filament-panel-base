<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Scaffolds `lang/{locale}/validation.php` for every locale the host
 * declares (or for an explicit list passed as arguments). The seed
 * content is Laravel's bundled English validation file — a known-good
 * starting template the host can translate without us shipping
 * potentially-wrong translations for non-English locales.
 *
 * Production tip: install `laravel-lang/lang` for community-maintained
 * translations across 70+ locales — then this command becomes a fallback
 * for locales that package doesn't cover.
 *
 *   php artisan filament-panel-base:scaffold-validation
 *   php artisan filament-panel-base:scaffold-validation ar fr de
 *   php artisan filament-panel-base:scaffold-validation --force
 */
class ScaffoldValidationLangCommand extends Command
{
    protected $signature = 'filament-panel-base:scaffold-validation
        {locales?* : Locale codes to scaffold (defaults to config(filament-panel-base.locale.available))}
        {--force : Overwrite existing validation.php files}';

    protected $description = 'Seed lang/{locale}/validation.php files for declared locales (English template).';

    public function handle(Filesystem $files): int
    {
        $locales = $this->argument('locales');

        if (empty($locales)) {
            $locales = (array) config('filament-panel-base.locale.available', ['en']);
        }

        $source = $this->resolveSource();

        if ($source === null) {
            $this->error('Could not locate Laravel\'s bundled lang/en/validation.php — Laravel install incomplete?');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $written = 0;
        $skipped = 0;

        foreach ($locales as $locale) {
            $target = lang_path("{$locale}/validation.php");

            if ($files->exists($target) && ! $force) {
                $this->line("  <fg=yellow>skip</> {$target} (already exists — pass --force to overwrite)");
                $skipped++;
                continue;
            }

            $files->ensureDirectoryExists(dirname($target));
            $files->copy($source, $target);
            $this->line("  <fg=green>wrote</> {$target}");
            $written++;
        }

        $this->newLine();
        $this->info("Done. {$written} scaffolded, {$skipped} skipped.");
        $this->line('Translate the strings in each file (or install laravel-lang/lang for community translations).');

        return self::SUCCESS;
    }

    protected function resolveSource(): ?string
    {
        $candidates = [
            base_path('lang/en/validation.php'),
            base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php'),
            // Reflect on the installed Translator class so we work even when
            // base_path() resolves to a testbench shim that doesn't ship a
            // local vendor copy.
            dirname((new \ReflectionClass(\Illuminate\Translation\Translator::class))->getFileName())
                .'/lang/en/validation.php',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
