<?php

namespace App\Filament\Admin\Resources\GalleryItems\Pages;

use App\Filament\Admin\Resources\GalleryItems\GalleryItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGalleryItems extends ManageRecords
{
    protected static string $resource = GalleryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
