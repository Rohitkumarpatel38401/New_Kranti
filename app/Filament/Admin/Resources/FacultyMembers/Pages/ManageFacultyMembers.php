<?php

namespace App\Filament\Admin\Resources\FacultyMembers\Pages;

use App\Filament\Admin\Resources\FacultyMembers\FacultyMemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFacultyMembers extends ManageRecords
{
    protected static string $resource = FacultyMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
