<?php

namespace App\Filament\Institute\Resources\Students\Pages;

use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Resources\Students\StudentResource;
use App\Filament\Shared\StudentForm;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateStudent extends CreateRecord
{
    use HasWizard;

    protected static string $resource = StudentResource::class;

    public function getSteps(): array
    {
        return StudentForm::steps();
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return StudentForm::service()->create(...[...StudentForm::split($data), auth('institute')->user()]);
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
