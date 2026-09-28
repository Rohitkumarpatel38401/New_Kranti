<?php

namespace App\Filament\Institute\Resources\Staff\Pages;

use App\Filament\Institute\Resources\Staff\StaffResource;
use App\Services\StaffService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $role = Arr::pull($data, 'role');

        return app(StaffService::class)->create($data, $role, auth('institute')->user());
    }
}
