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
    protected $signature = 'filament-panel-base:enable-translations';

    protected $description = 'Publish translation-loader migration & config, then print activation instructions.';

    /**
     * Publish tags exposed by spatie/laravel-translation-loader's
     * PackageServiceProvider. laravel-package-tools names every tag
     * "<short name>-<group>", where the short name is the package name with
     * the leading "laravel-" stripped.
     */
    private const PUBLISH_TAGS = [
        'translation-loader-config',
        'translation-loader-migrations',
    ];

    public function handle(): int
    {
        $this->info('Publishing spatie/laravel-translation-loader assets...');

        // spatie/laravel-translation-loader builds its provider with
        // laravel-package-tools, which prefixes every publish tag with the
        // short name. Publishing under the bare 'config'/'migrations'
        // tags matches nothing and exits 0 with "No publishable resources",
        // so the app silently ends up with ->withTranslations() enabled and no
        // language_lines table — every __() then throws a QueryException and
        // probes Schema::hasTable() once per translation group, per request.
        foreach (self::PUBLISH_TAGS as $tag) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\TranslationLoader\TranslationServiceProvider',
                '--tag' => $tag,
            ]);
        }

        $this->newLine();
        $this->info('Done! Follow these steps to activate the translation manager:');
        $this->newLine();

        $this->line('  <fg=yellow>1.</> Add a <fg=cyan>deleted_at</> column to the published migration, then run it:');
        $this->newLine();
        $this->line('     <fg=gray>$table-></><fg=cyan>softDeletes</><fg=gray>();</>');
        $this->line('     <fg=cyan>php artisan migrate</>');
        $this->newLine();
        $this->warn('     The published stub comes from spatie and has no deleted_at, but the');
        $this->warn('     panel-base Translation model is a SoftDeletes LanguageLine.');
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
