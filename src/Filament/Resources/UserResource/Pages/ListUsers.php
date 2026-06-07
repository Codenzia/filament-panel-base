<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Filament\Resources\UserResource\Pages;

use Codenzia\FilamentPanelBase\Filament\Resources\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('New user'))
                ->icon('heroicon-o-plus')
                ->slideOver()
                ->createAnother(false),
        ];
    }
}
