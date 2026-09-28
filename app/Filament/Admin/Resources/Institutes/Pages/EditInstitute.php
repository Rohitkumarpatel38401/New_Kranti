<?php

namespace App\Filament\Admin\Resources\Institutes\Pages;

use App\Enums\AddressType;
use App\Filament\Admin\Resources\Institutes\InstituteResource;
use App\Filament\Shared\AddressFields;
use App\Services\InstituteService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditInstitute extends EditRecord
{
    protected static string $resource = InstituteResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $profile = $this->record->profile;

        $data['profile'] = $profile ? Arr::except($profile->attributesToArray(), ['id', 'institute_id', 'created_at', 'updated_at']) : [];
        $data['office_address'] = $this->record->address(AddressType::Office)?->only(AddressFields::KEYS) ?? [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $profile = Arr::pull($data, 'profile', []);
        $address = Arr::pull($data, 'office_address', []);

        return app(InstituteService::class)->update($record, $data, $profile, $address);
    }
}
