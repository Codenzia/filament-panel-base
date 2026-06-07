<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Filament\Resources\UserResource\Pages;

use Codenzia\FilamentPanelBase\Filament\Resources\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Guard the protected super-admin from deletion here too.
            DeleteAction::make()->visible(fn (): bool => ! (bool) ($this->record->is_protected ?? false)),
        ];
    }
}
