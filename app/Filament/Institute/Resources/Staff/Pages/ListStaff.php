<?php

namespace App\Filament\Institute\Resources\Staff\Pages;

use App\Filament\Institute\Resources\Staff\StaffResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
