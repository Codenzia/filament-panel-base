<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Filament\Resources\TranslationResource\Pages;

use Codenzia\FilamentPanelBase\Filament\Resources\TranslationResource;
use Codenzia\FilamentPanelBase\Models\Translation;
use Codenzia\FilamentPanelBase\Services\TranslationScanner;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use League\Csv\Reader;
use League\Csv\Writer;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageTranslations extends ManageRecords
{
    protected static string $resource = TranslationResource::class;

    #[Url]
    public ?string $locale = null;

    public function getHeading(): string|Htmlable
    {
        if ($this->locale) {
            return __('UI Translations') . ' — ' . strtoupper($this->locale);
        }

        return __('UI Translations');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->alpineClickHandler('window.history.back()'),

            Actions\Action::make('scan')
                ->label(__('Scan'))
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('Scan for Translation Keys'))
                ->modalDescription(__('This will scan your codebase for all translation keys and sync them to the database. Existing translations will be preserved.'))
                ->action(function (): void {
                    $scanner = app(TranslationScanner::class);
                    $result = $scanner->scan();

                    Notification::make()
                        ->title(__('Scan Complete'))
                        ->body(__(':created new, :restored restored, :total total keys', $result))
                        ->success()
                        ->send();
                }),
            $this->getImportAction(),
            $this->getExportAction(),

        ];
    }

    private function getExportAction(): Actions\Action
    {
        return Actions\Action::make('export')
            ->label(__('Export'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function (): StreamedResponse {
                $locales = $this->locale
                    ? [$this->locale]
                    : Translation::getLocales();

                $translations = Translation::query()
                    ->whereNull('deleted_at')
                    ->orderBy('key')
                    ->get();

                $suffix = $this->locale ? $this->locale : 'all';
                $filename = 'ui-translations-' . $suffix . '-' . date('Y-m-d') . '.csv';

                return response()->streamDownload(function () use ($translations, $locales): void {
                    $csv = Writer::createFromStream(fopen('php://output', 'w'));

                    // UTF-8 BOM for Excel compatibility
                    $csv->setOutputBOM(Writer::BOM_UTF8);

                    $csv->insertOne(['key', ...$locales]);

                    foreach ($translations as $translation) {
                        $row = [$translation->key];

                        foreach ($locales as $locale) {
                            $row[] = $translation->text[$locale] ?? '';
                        }

                        $csv->insertOne($row);
                    }
                }, $filename, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ]);
            });
    }

    private function getImportAction(): Actions\Action
    {
        return Actions\Action::make('import')
            ->label(__('Import'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('info')
            ->form([
                Forms\Components\FileUpload::make('file')
                    ->label(__('CSV File'))
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                    ->required()
                    ->disk('local')
                    ->directory('tmp/translation-imports')
                    ->helperText(__('Upload a CSV file with "key" column and locale columns (e.g. en, ar).')),
            ])
            ->modalHeading(__('Import UI Translations'))
            ->modalDescription(__('Upload a CSV file exported from this page. Only existing keys will be updated — new keys are not created.'))
            ->action(function (array $data): void {
                $path = storage_path('app/private/' . $data['file']);

                if (! file_exists($path)) {
                    $path = storage_path('app/' . $data['file']);
                }

                $csv = Reader::createFromPath($path, 'r');
                $csv->setHeaderOffset(0);

                $headers = $csv->getHeader();
                $keyIndex = array_search('key', $headers, true);

                if ($keyIndex === false) {
                    Notification::make()
                        ->title(__('Import Failed'))
                        ->body(__('CSV file must have a "key" column.'))
                        ->danger()
                        ->send();

                    return;
                }

                // Determine which locale columns are present
                $allLocales = Translation::getLocales();
                $csvLocales = array_values(array_intersect($headers, $allLocales));

                if (empty($csvLocales)) {
                    Notification::make()
                        ->title(__('Import Failed'))
                        ->body(__('CSV file must have at least one locale column (e.g. en, ar).'))
                        ->danger()
                        ->send();

                    return;
                }

                $updated = 0;
                $skipped = 0;

                foreach ($csv->getRecords() as $record) {
                    $key = trim($record['key'] ?? '');

                    if ($key === '') {
                        $skipped++;

                        continue;
                    }

                    $translation = Translation::query()
                        ->where('key', $key)
                        ->whereNull('deleted_at')
                        ->first();

                    if (! $translation) {
                        $skipped++;

                        continue;
                    }

                    $text = $translation->text ?? [];
                    $changed = false;

                    foreach ($csvLocales as $locale) {
                        $value = $record[$locale] ?? '';

                        if ($value !== ($text[$locale] ?? '')) {
                            $text[$locale] = $value;
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        $translation->update(['text' => $text]);
                        $updated++;
                    }
                }

                // Clean up temp file
                @unlink($path);

                Notification::make()
                    ->title(__('Import Complete'))
                    ->body(__(':updated translations updated, :skipped rows skipped.', [
                        'updated' => $updated,
                        'skipped' => $skipped,
                    ]))
                    ->success()
                    ->send();
            });
    }
}
