<?php

namespace App\Filament\Institute\Resources\Staff\Pages;

use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Resources\Staff\StaffResource;
use App\Services\StaffService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'role' => $this->record->roles->first()?->name, 'password' => null];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $role = Arr::pull($data, 'role');

        try {
            return app(StaffService::class)->update($record, $data, $role, auth('institute')->user());
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            throw new Halt;
        }
    }
}
