<?php

namespace App\Filament\Admin\Resources\AcademicSessions\Pages;

use App\Filament\Admin\Resources\AcademicSessions\AcademicSessionResource;
use App\Services\AcademicSessionService;
use Filament\Resources\Pages\EditRecord;

class EditAcademicSession extends EditRecord
{
    protected static string $resource = AcademicSessionResource::class;

    protected function afterSave(): void
    {
        if ($this->record->is_current) {
            app(AcademicSessionService::class)->makeCurrent($this->record);
        }
    }
}
