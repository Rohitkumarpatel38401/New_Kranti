<?php

namespace App\Filament\Admin\Resources\Admins\Pages;

use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Admins\AdminResource;
use App\Services\AdminTeamService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'roles' => $this->record->roles->pluck('name')->all(), 'password' => null];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $roles = Arr::pull($data, 'roles');

        try {
            return app(AdminTeamService::class)->update($record, $data, $roles, auth('admin')->user());
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            throw new Halt;
        }
    }
}
