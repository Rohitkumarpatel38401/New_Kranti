<?php

namespace App\Filament\Institute\Pages;

use App\Filament\Shared\DuesTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class Dues extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.shared.table-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Fee dues';

    public static function canAccess(): bool
    {
        return (bool) auth('institute')->user()?->can('collect_fee');
    }

    public function table(Table $table): Table
    {
        return DuesTable::configure($table);
    }
}
