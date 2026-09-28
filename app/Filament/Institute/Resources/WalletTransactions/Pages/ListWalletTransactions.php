<?php

namespace App\Filament\Institute\Resources\WalletTransactions\Pages;

use App\Filament\Institute\Resources\WalletRecharges\WalletRechargeResource;
use App\Filament\Institute\Resources\WalletTransactions\WalletTransactionResource;
use App\Services\WalletService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListWalletTransactions extends ListRecords
{
    protected static string $resource = WalletTransactionResource::class;

    public function getSubheading(): ?string
    {
        $institute = auth('institute')->user()?->institute;

        return $institute ? 'Current balance: ₹'.app(WalletService::class)->balance($institute) : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recharge')
                ->label('Recharge wallet')
                ->url(WalletRechargeResource::getUrl('create'))
                ->visible(fn () => WalletRechargeResource::canCreate()),
        ];
    }
}
