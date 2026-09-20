<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Filament\Resources;

use Codenzia\FilamentPanelBase\Filament\Resources\TranslationResource\Pages;
use Codenzia\FilamentPanelBase\Models\Translation;
use Codenzia\FilamentPanelBase\Services\TranslationScanner;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class TranslationResource extends Resource
{
    protected static ?string $model = Translation::class;

    protected static ?string $recordTitleAttribute = 'key';

    protected static bool $isScopedToTenant = false;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationIcon(): string|\BackedEnum
    {
        return config('filament-panel-base.translations.navigation_icon', 'heroicon-o-language');
    }

    public static function getNavigationGroup(): ?string
    {
        return fpb_trans(config('filament-panel-base.translations.navigation_group', 'Settings'));
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-panel-base.translations.navigation_sort', 11);
    }

    public static function getModelLabel(): string
    {
        return fpb_trans('UI Translation');
    }

    public static function getPluralModelLabel(): string
    {
        return fpb_trans('UI Translations');
    }

    /**
     * Table record action for managing translations, scoped to a language record's locale.
     *
     * Usage in a LanguageResource:
     *   TranslationResource::manageAction()
     */
    public static function manageAction(string $localeAttribute = 'code'): Actions\Action
    {
        return Actions\Action::make('manageTranslations')
            ->label(fpb_trans('Manage UI Translations'))
            ->icon('heroicon-o-language')
            ->url(fn ($record): string => static::getUrl('index').'?'.http_build_query([
                'locale' => data_get($record, $localeAttribute),
            ]));
    }

    /**
     * Header action for scanning the codebase for translation keys.
     *
     * Usage in a ManageLanguages page:
     *   TranslationResource::scanHeaderAction()
     */
    public static function scanHeaderAction(): Actions\Action
    {
        return Actions\Action::make('scanTranslations')
            ->label(fpb_trans('Scan Translations'))
            ->icon('heroicon-o-magnifying-glass')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fpb_trans('Scan for Translation Keys'))
            ->modalDescription(fpb_trans('This will scan your codebase for all translation keys and sync them to the database. Existing translations will be preserved.'))
            ->action(function (): void {
                $scanner = app(TranslationScanner::class);
                $result = $scanner->scan();

                Notification::make()
                    ->title(fpb_trans('Scan Complete'))
                    ->body(fpb_trans(':created new, :restored restored, :total total keys', $result))
                    ->success()
                    ->send();
            });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Forms\Components\Hidden::make('group')
                        ->default('*'),

                    Forms\Components\Textarea::make('key')
                        ->label(fpb_trans('Original Text'))
                        ->required()
                        ->disabled(fn (?Translation $record): bool => $record !== null)
                        ->rows(2),

                    ...static::getLocaleFields(),
                ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('key')
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label(fpb_trans('Key'))
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                Tables\Columns\TextInputColumn::make('value')
                    ->label(fpb_trans('Value'))
                    ->getStateUsing(function (Translation $record, $livewire): string {
                        $locale = $livewire->locale
                            ?? Translation::getLocales()[0]
                            ?? 'en';

                        return $record->text[$locale] ?? '';
                    })
                    ->updateStateUsing(function (Translation $record, string $state, $livewire): void {
                        $locale = $livewire->locale
                            ?? Translation::getLocales()[0]
                            ?? 'en';

                        $record->setTranslation($locale, $state);
                        $record->save();
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(fpb_trans('Updated'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions(ActionGroup::make([
                Actions\EditAction::make(),
            ]));
    }

    public static function getNavigationBadge(): ?string
    {
        return Cache::remember(
            'fpb.nav-badge.translations',
            300,
            fn (): string => (string) (static::getModel()::count() ?: null),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTranslations::route('/'),
        ];
    }

    /**
     * Build a Textarea field for each active locale.
     *
     * When the ManageTranslations page has a #[Url] locale property set
     * (via the Language "Manage Translations" action), only the matching
     * locale field is visible. Hidden locale fields stay dehydrated so
     * existing translations for other locales are preserved on save.
     *
     * @return array<Forms\Components\Textarea>
     */
    private static function getLocaleFields(): array
    {
        $locales = Translation::getLocales();

        return collect($locales)
            ->map(fn (string $locale): Forms\Components\Textarea => Forms\Components\Textarea::make("text.{$locale}")
                ->label(strtoupper($locale))
                ->rows(3)
                ->visible(fn ($livewire): bool => ! $livewire?->locale || $livewire->locale === $locale)
                ->dehydrated())
            ->all();
    }
}
