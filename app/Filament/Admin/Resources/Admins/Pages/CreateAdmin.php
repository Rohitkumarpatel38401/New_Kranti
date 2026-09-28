<?php

namespace App\Filament\Admin\Resources\Admins\Pages;

use App\Filament\Admin\Resources\Admins\AdminResource;
use App\Services\AdminTeamService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateAdmin extends CreateRecord
{
    protected static string $resource = AdminResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $roles = Arr::pull($data, 'roles', []);

        return app(AdminTeamService::class)->create($data, $roles, auth('admin')->user());
    }
}
