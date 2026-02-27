<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Commands;

use Illuminate\Console\Command;

/**
 * Artisan command that bootstraps the TomatoPHP filament-translations package.
 *
 * Runs the upstream install command (publishes migrations and config),
 * then prints step-by-step instructions to the developer.
 */
class EnableTranslationsCommand extends Command
{
    protected $signature = 'panel-base:enable-translations';

    protected $description = 'Publish TomatoPHP filament-translations migrations & config, then print activation instructions.';

    public function handle(): int
    {
        $this->info('Running filament-translations:install...');

        $exitCode = $this->call('filament-translations:install');

        if ($exitCode !== Command::SUCCESS) {
            $this->error('filament-translations:install failed. Check the output above.');

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('Done! Follow these steps to activate the translation manager:');
        $this->newLine();

        $this->line('  <fg=yellow>1.</> Run your migrations:');
        $this->line('     <fg=cyan>php artisan migrate</>');
        $this->newLine();

        $this->line('  <fg=yellow>2.</> Add <fg=cyan>->withTranslations()</> to FilamentPanelBasePlugin in your panel provider:');
        $this->newLine();
        $this->line('     <fg=gray>->plugins([</>');
        $this->line('         <fg=white>FilamentPanelBasePlugin::make()</>');
        $this->line('             <fg=cyan>->withTranslations()</>');
        $this->line('             <fg=gray>->settingsUsing(...),</>');
        $this->line('     <fg=gray>])</>');
        $this->newLine();

        $this->line('  <fg=yellow>3.</> (Optional) Review <fg=cyan>config/filament-translations.php</> to configure');
        $this->line('     scan paths, UI options, and queue settings.');
        $this->newLine();

        $this->info('Translation manager is ready to activate.');

        return Command::SUCCESS;
    }
}
