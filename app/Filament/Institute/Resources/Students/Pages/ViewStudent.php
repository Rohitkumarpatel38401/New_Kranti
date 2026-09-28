<?php

namespace App\Filament\Institute\Resources\Students\Pages;

use App\Filament\Institute\Resources\Students\StudentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
