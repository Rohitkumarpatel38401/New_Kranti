<?php

namespace App\Filament\Admin\Resources\Notices\Pages;

use App\Filament\Admin\Resources\Notices\NoticeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNotices extends ManageRecords
{
    protected static string $resource = NoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
