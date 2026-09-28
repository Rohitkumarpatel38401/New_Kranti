<?php

namespace App\Filament\Admin\Resources\Institutes\Pages;

use App\Filament\Admin\Resources\Institutes\InstituteResource;
use App\Services\InstituteService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateInstitute extends CreateRecord
{
    protected static string $resource = InstituteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $profile = Arr::pull($data, 'profile', []);
        $address = Arr::pull($data, 'office_address', []);

        return app(InstituteService::class)->create($data, $profile, $address, auth('admin')->user());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
