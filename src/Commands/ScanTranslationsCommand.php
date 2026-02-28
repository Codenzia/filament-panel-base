<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Commands;

use Codenzia\FilamentPanelBase\Services\TranslationScanner;
use Illuminate\Console\Command;

class ScanTranslationsCommand extends Command
{
    protected $signature = 'translations:scan';

    protected $description = 'Scan the codebase for translation keys and sync them to the database.';

    public function handle(TranslationScanner $scanner): int
    {
        $this->info('Scanning for translation keys...');

        $result = $scanner->scan();

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['New keys created', $result['created']],
                ['Existing keys restored', $result['restored']],
                ['Total active keys', $result['total']],
            ],
        );

        $this->newLine();
        $this->info('Scan complete.');

        return self::SUCCESS;
    }
}
