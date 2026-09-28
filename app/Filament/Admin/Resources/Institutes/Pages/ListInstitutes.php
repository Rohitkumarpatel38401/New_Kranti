<?php

namespace App\Filament\Admin\Resources\Institutes\Pages;

use App\Filament\Admin\Resources\Institutes\InstituteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstitutes extends ListRecords
{
    protected static string $resource = InstituteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
