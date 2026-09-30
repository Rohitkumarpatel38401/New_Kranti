<?php

namespace App\Filament\Student\Resources\MyEnrollments\Pages;

use App\Filament\Student\Resources\MyEnrollments\MyEnrollmentResource;
use Filament\Resources\Pages\ListRecords;

class ListMyEnrollments extends ListRecords
{
    protected static string $resource = MyEnrollmentResource::class;
}
