<?php

namespace App\Filament\Student\Resources\MyPayments\Pages;

use App\Filament\Student\Resources\MyPayments\MyPaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListMyPayments extends ListRecords
{
    protected static string $resource = MyPaymentResource::class;
}
