<?php

namespace App\Filament\Shared;

use App\Enums\AttendanceStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Attendance sessions with head counts, shared by the institute and HQ lists.
 */
class AttendanceHistoryTable
{
    public static function configure(Table $table): Table
    {
        $count = fn (AttendanceStatus ...$statuses) => fn (Builder $q) => $q->whereIn('status', array_map(fn ($s) => $s->value, $statuses));

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['batch.course', 'takenBy'])
                ->withCount(['records as attended_count' => $count(AttendanceStatus::Present, AttendanceStatus::Late),
                    'records as absent_count' => $count(AttendanceStatus::Absent),
                    'records as marked_count']))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('batch.name')->label('Batch')->searchable(),
                TextColumn::make('batch.course.code')->label('Course'),
                TextColumn::make('attended_count')->label('Present / late'),
                TextColumn::make('absent_count')->label('Absent'),
                TextColumn::make('marked_count')->label('Marked'),
                TextColumn::make('takenBy.name')->label('Taken by')->placeholder('—'),
            ])
            ->filters([
                Filter::make('date_range')->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('date', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('date', '<=', $d))),
            ]);
    }
}
