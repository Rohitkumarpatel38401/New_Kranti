<?php

namespace App\Filament\Admin\Resources\Institutes\Pages;

use App\Filament\Admin\Resources\Institutes\InstituteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInstitute extends ViewRecord
{
    protected static string $resource = InstituteResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
