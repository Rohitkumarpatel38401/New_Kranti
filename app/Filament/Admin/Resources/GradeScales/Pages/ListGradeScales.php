<?php

namespace App\Filament\Admin\Resources\GradeScales\Pages;

use App\Filament\Admin\Resources\GradeScales\GradeScaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGradeScales extends ListRecords
{
    protected static string $resource = GradeScaleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
