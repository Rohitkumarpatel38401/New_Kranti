<?php

namespace App\Filament\Admin\Resources\WalletTransactions;

use App\Filament\Admin\Resources\WalletTransactions\Pages\ListWalletTransactions;
use App\Filament\Exports\WalletTransactionExporter;
use App\Filament\Institute\Resources\WalletTransactions\WalletTransactionResource as InstituteLedger;
use App\Models\Institute;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Wallets';

    protected static ?string $navigationLabel = 'Ledger';

    public static function table(Table $table): Table
    {
        return InstituteLedger::ledgerTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('institute'))
            ->pushColumns([
                TextColumn::make('institute.code')->label('Institute')->searchable()
                    ->description(fn (WalletTransaction $r) => $r->institute?->name),
            ])
            ->headerActions([ExportAction::make()->exporter(WalletTransactionExporter::class)])
            ->pushFilters([
                SelectFilter::make('institute_id')->label('Institute')
                    ->options(fn () => Institute::query()->orderBy('code')->pluck('name', 'id'))->searchable(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWalletTransactions::route('/')];
    }
}
