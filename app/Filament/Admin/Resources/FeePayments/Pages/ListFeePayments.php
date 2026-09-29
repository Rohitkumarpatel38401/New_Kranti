<?php

namespace App\Filament\Admin\Resources\FeePayments\Pages;

use App\Filament\Admin\Resources\FeePayments\FeePaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListFeePayments extends ListRecords
{
    protected static string $resource = FeePaymentResource::class;
}
