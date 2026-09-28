<?php

namespace App\Filament\Institute\Resources\Students\Pages;

use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Resources\Students\StudentResource;
use App\Filament\Shared\StudentForm;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return StudentForm::fill($this->record);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return StudentForm::service()->update($record, ...StudentForm::split($data));
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            throw new Halt;
        }
    }
}
