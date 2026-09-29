<?php

namespace App\Filament\Admin\Resources\FeePayments;

use App\Filament\Admin\Resources\FeePayments\Pages\ListFeePayments;
use App\Filament\Exports\FeePaymentExporter;
use App\Filament\Institute\Resources\FeePayments\FeePaymentResource as InstituteReceipts;
use App\Models\FeePayment;
use App\Models\Institute;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class FeePaymentResource extends Resource
{
    protected static ?string $model = FeePayment::class;

    protected static ?string $slug = 'fee-receipts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Fees';

    protected static ?string $navigationLabel = 'Receipts';

    protected static ?string $modelLabel = 'receipt';

    public static function table(Table $table): Table
    {
        $table = InstituteReceipts::receiptsTable($table, fn () => auth('admin')->user());

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('institute'))
            ->pushColumns([
                TextColumn::make('institute.code')->label('Institute')->searchable()
                    ->description(fn (FeePayment $r) => $r->institute?->name),
            ])
            ->pushFilters([
                SelectFilter::make('institute_id')->label('Institute')->searchable()
                    ->options(fn () => Institute::query()->orderBy('code')->get()->mapWithKeys(fn (Institute $i) => [$i->id => "{$i->code} — {$i->name}"])),
            ])
            ->headerActions([ExportAction::make()->exporter(FeePaymentExporter::class)]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFeePayments::route('/')];
    }
}
