<?php

namespace App\Filament\Institute\Resources\AttendanceSessions;

use App\Filament\Institute\Resources\AttendanceSessions\Pages\ListAttendanceSessions;
use App\Filament\Shared\AttendanceHistoryTable;
use App\Models\AttendanceSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AttendanceSessionResource extends Resource
{
    protected static ?string $model = AttendanceSession::class;

    protected static ?string $slug = 'attendance-history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'History';

    public static function table(Table $table): Table
    {
        return AttendanceHistoryTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListAttendanceSessions::route('/')];
    }
}
