<?php

namespace App\Filament\Admin\Resources\AttendanceSessions;

use App\Filament\Admin\Resources\AttendanceSessions\Pages\ListAttendanceSessions;
use App\Filament\Shared\AttendanceHistoryTable;
use App\Models\AttendanceSession;
use App\Models\Institute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AttendanceSessionResource extends Resource
{
    protected static ?string $model = AttendanceSession::class;

    protected static ?string $slug = 'attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Students';

    protected static ?string $navigationLabel = 'Attendance';

    public static function table(Table $table): Table
    {
        return AttendanceHistoryTable::configure($table)
            ->modifyQueryUsing(fn ($query) => $query->with('institute'))
            ->pushColumns([TextColumn::make('institute.code')->label('Institute')->description(fn (AttendanceSession $r) => $r->institute?->name)])
            ->pushFilters([
                SelectFilter::make('institute_id')->label('Institute')->searchable()
                    ->options(fn () => Institute::query()->orderBy('code')->get()->mapWithKeys(fn (Institute $i) => [$i->id => "{$i->code} — {$i->name}"])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAttendanceSessions::route('/')];
    }
}
