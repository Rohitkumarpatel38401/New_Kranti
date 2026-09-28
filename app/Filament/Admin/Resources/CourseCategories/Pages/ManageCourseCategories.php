<?php

namespace App\Filament\Admin\Resources\CourseCategories\Pages;

use App\Filament\Admin\Resources\CourseCategories\CourseCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCourseCategories extends ManageRecords
{
    protected static string $resource = CourseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
