<?php

namespace App\Filament\Institute\Resources\Students\Pages;

use App\Filament\Institute\Resources\Enrollments\EnrollmentResource;
use App\Filament\Institute\Resources\Students\StudentResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enroll')
                ->label('Enroll in a course')
                ->url(fn () => EnrollmentResource::getUrl('create', ['student' => $this->record->id]))
                ->visible(fn () => EnrollmentResource::canCreate()),
            EditAction::make(),
        ];
    }
}
