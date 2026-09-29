<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Shared\DuesTable;
use App\Models\FeePayment;
use App\Models\Institute;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class Dues extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.shared.table-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Fees';

    protected static ?string $title = 'Fee dues';

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->can('viewAny', FeePayment::class);
    }

    public function table(Table $table): Table
    {
        return DuesTable::configure($table, showInstitute: true)
            ->pushFilters([
                SelectFilter::make('institute_id')->label('Institute')->searchable()
                    ->options(fn () => Institute::query()->orderBy('code')->get()->mapWithKeys(fn (Institute $i) => [$i->id => "{$i->code} — {$i->name}"])),
            ]);
    }
}
