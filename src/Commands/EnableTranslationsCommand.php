<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Commands;

use Illuminate\Console\Command;

/**
 * Artisan command that bootstraps the built-in translation manager.
 *
 * Publishes the spatie/laravel-translation-loader migration and config,
 * then prints step-by-step activation instructions.
 */
class EnableTranslationsCommand extends Command
{
    protected $signature = 'panel-base:enable-translations';

    protected $description = 'Publish translation-loader migration & config, then print activation instructions.';

    public function handle(): int
    {
        $this->info('Publishing spatie/laravel-translation-loader assets...');

        $this->call('vendor:publish', [
            '--provider' => 'Spatie\TranslationLoader\TranslationServiceProvider',
            '--tag' => 'config',
        ]);

        $this->call('vendor:publish', [
            '--provider' => 'Spatie\TranslationLoader\TranslationServiceProvider',
            '--tag' => 'migrations',
        ]);

        $this->newLine();
        $this->info('Done! Follow these steps to activate the translation manager:');
        $this->newLine();

        $this->line('  <fg=yellow>1.</> Run your migrations:');
        $this->line('     <fg=cyan>php artisan migrate</>');
        $this->newLine();

        $this->line('  <fg=yellow>2.</> Update <fg=cyan>config/translation-loader.php</> to use the panel-base model:');
        $this->newLine();
        $this->line("     <fg=gray>'model' => </><fg=cyan>Codenzia\\FilamentPanelBase\\Models\\Translation::class</><fg=gray>,</>");
        $this->newLine();

        $this->line('  <fg=yellow>3.</> Add <fg=cyan>->withTranslations()</> to FilamentPanelBasePlugin in your panel provider:');
        $this->newLine();
        $this->line('     <fg=gray>->plugins([</>');
        $this->line('         <fg=white>FilamentPanelBasePlugin::make()</>');
        $this->line('             <fg=cyan>->withTranslations()</>');
        $this->line('             <fg=gray>->settingsUsing(...),</>');
        $this->line('     <fg=gray>])</>');
        $this->newLine();

        $this->line('  <fg=yellow>4.</> Scan your codebase for translation keys:');
        $this->line('     <fg=cyan>php artisan translations:scan</>');
        $this->newLine();

        $this->info('Translation manager is ready to activate.');

        return Command::SUCCESS;
    }
}
