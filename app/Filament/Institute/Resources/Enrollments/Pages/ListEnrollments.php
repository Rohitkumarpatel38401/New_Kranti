<?php

namespace App\Filament\Institute\Resources\Enrollments\Pages;

use App\Filament\Institute\Resources\Enrollments\EnrollmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEnrollments extends ListRecords
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
