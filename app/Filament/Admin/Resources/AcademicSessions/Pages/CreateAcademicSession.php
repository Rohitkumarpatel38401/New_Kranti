<?php

namespace App\Filament\Admin\Resources\AcademicSessions\Pages;

use App\Filament\Admin\Resources\AcademicSessions\AcademicSessionResource;
use App\Services\AcademicSessionService;
use Filament\Resources\Pages\CreateRecord;

class CreateAcademicSession extends CreateRecord
{
    protected static string $resource = AcademicSessionResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->is_current) {
            app(AcademicSessionService::class)->makeCurrent($this->record);
        }
    }
}
