<?php

namespace App\Filament\Shared;

use App\Enums\ResultOutcome;
use App\Enums\ResultStatus;
use App\Models\ExamResult;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Results list shared by the institute and HQ panels.
 */
class ResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['enrollment.student', 'enrollment.course', 'marks.courseSubject.subject']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('enrollment.student.registration_no')->label('Reg. no.')
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('enrollment.student', fn (Builder $st) => $st
                        ->where('registration_no', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%"))),
                TextColumn::make('student')->label('Student')->state(fn (ExamResult $r) => $r->enrollment?->student?->full_name),
                TextColumn::make('enrollment.course.code')->label('Course'),
                TextColumn::make('exam_date')->date(),
                TextColumn::make('status')->badge()->color(fn (ResultStatus $state) => match ($state) {
                    ResultStatus::Published => 'success',
                    ResultStatus::Submitted => 'warning',
                    ResultStatus::Returned => 'danger',
                    ResultStatus::Draft => 'gray',
                }),
                TextColumn::make('percentage')->suffix('%')->placeholder('—'),
                TextColumn::make('grade')->placeholder('—'),
                TextColumn::make('result')->badge()->placeholder('—')
                    ->color(fn (?ResultOutcome $state) => $state === ResultOutcome::Pass ? 'success' : 'danger'),
                TextColumn::make('return_remarks')->label('HQ remarks')->placeholder('—')->wrap()->toggleable(),
            ])
            ->filters([SelectFilter::make('status')->options(ResultStatus::class)]);
    }

    public static function viewMarksAction(): Action
    {
        return Action::make('marks')
            ->label('Marks')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalSubmitAction(false)
            ->schema([
                RepeatableEntry::make('marks')->hiddenLabel()->columns(5)->schema([
                    TextEntry::make('courseSubject.subject.code')->label('Subject'),
                    TextEntry::make('theory')->placeholder('—'),
                    TextEntry::make('practical')->placeholder('—'),
                    TextEntry::make('total'),
                    TextEntry::make('is_absent')->label('Absent')->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),
                ]),
            ]);
    }
}
