<?php

namespace App\Filament\Exports;

use App\Models\WalletTransaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class WalletTransactionExporter extends Exporter
{
    protected static ?string $model = WalletTransaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('When'),
            ExportColumn::make('institute.code')->label('Institute code'),
            ExportColumn::make('institute.name')->label('Institute'),
            ExportColumn::make('type')->formatStateUsing(fn ($state) => $state?->label()),
            ExportColumn::make('source')->formatStateUsing(fn ($state) => $state?->label()),
            ExportColumn::make('amount'),
            ExportColumn::make('balance_after'),
            ExportColumn::make('description'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return "Wallet ledger export finished: {$export->successful_rows} rows.";
    }
}
