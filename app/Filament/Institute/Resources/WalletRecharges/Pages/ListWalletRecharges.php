<?php

namespace App\Filament\Institute\Resources\WalletRecharges\Pages;

use App\Filament\Institute\Resources\WalletRecharges\WalletRechargeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWalletRecharges extends ListRecords
{
    protected static string $resource = WalletRechargeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
