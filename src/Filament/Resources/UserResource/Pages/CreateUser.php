<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Filament\Resources\UserResource\Pages;

use Codenzia\FilamentPanelBase\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static bool $canCreateAnother = false;
}
