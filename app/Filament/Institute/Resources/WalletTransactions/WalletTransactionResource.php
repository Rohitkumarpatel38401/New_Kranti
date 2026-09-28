<?php

namespace App\Filament\Institute\Resources\WalletTransactions;

use App\Enums\WalletTransactionType;
use App\Filament\Institute\Resources\WalletTransactions\Pages\ListWalletTransactions;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static ?string $slug = 'wallet';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Wallet';

    protected static ?string $navigationLabel = 'Balance & statement';

    protected static ?int $navigationSort = -1;

    public static function table(Table $table): Table
    {
        return self::ledgerTable($table);
    }

    /** Shared by the institute statement and the HQ ledger. */
    public static function ledgerTable(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime(),
                TextColumn::make('description')->wrap()->searchable(),
                TextColumn::make('source')->badge(),
                TextColumn::make('credit')->label('Credit')->money('INR')
                    ->state(fn (WalletTransaction $r) => $r->type === WalletTransactionType::Credit ? $r->amount : null),
                TextColumn::make('debit')->label('Debit')->money('INR')
                    ->state(fn (WalletTransaction $r) => $r->type === WalletTransactionType::Debit ? $r->amount : null),
                TextColumn::make('balance_after')->label('Balance')->money('INR'),
            ])
            ->filters([SelectFilter::make('type')->options(WalletTransactionType::class)]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWalletTransactions::route('/')];
    }
}
