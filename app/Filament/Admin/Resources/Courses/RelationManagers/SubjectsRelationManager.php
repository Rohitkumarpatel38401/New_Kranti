<?php

namespace App\Filament\Admin\Resources\Courses\RelationManagers;

use App\Models\CourseSubject;
use App\Models\Subject;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'courseSubjects';

    protected static ?string $title = 'Subjects & marks';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Select::make('subject_id')->label('Subject')->required()->searchable()->columnSpan(3)
                ->options(fn () => Subject::query()->where('is_active', true)->orderBy('code')->get()
                    ->mapWithKeys(fn (Subject $s) => [$s->id => "{$s->code} — {$s->localized('name')}"]))
                ->unique(table: 'course_subjects', column: 'subject_id', ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('course_id', $this->getOwnerRecord()->getKey()))
                ->validationMessages(['unique' => 'This subject is already part of the course.']),
            TextInput::make('term_no')->label('Term / semester')->required()->integer()->minValue(1)->default(1),
            TextInput::make('sort_order')->integer()->default(0),
            TextInput::make('theory_max')->label('Theory max')->required()->integer()->minValue(0)->default(0),
            TextInput::make('practical_max')->label('Practical max')->required()->integer()->minValue(0)->default(0),
            TextInput::make('pass_marks')->required()->integer()->minValue(0)
                ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                    if ((int) $value > (int) $get('theory_max') + (int) $get('practical_max')) {
                        $fail('Pass marks cannot exceed theory + practical maximum.');
                    }
                }]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('term_no')->label('Term'),
                TextColumn::make('subject.code')->label('Code'),
                TextColumn::make('subject')->label('Subject')
                    ->state(fn (CourseSubject $record) => $record->subject?->localized('name')),
                TextColumn::make('theory_max')->label('Theory'),
                TextColumn::make('practical_max')->label('Practical'),
                TextColumn::make('total')->state(fn (CourseSubject $record) => $record->maxMarks()),
                TextColumn::make('pass_marks')->label('Pass'),
            ])
            ->headerActions([CreateAction::make()->label('Add subject')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
