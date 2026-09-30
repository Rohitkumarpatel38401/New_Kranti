<?php

namespace App\Filament\Institute\Resources\Batches;

use App\Enums\BatchStatus;
use App\Filament\Institute\Resources\Batches\Pages\CreateBatch;
use App\Filament\Institute\Resources\Batches\Pages\EditBatch;
use App\Filament\Institute\Resources\Batches\Pages\ListBatches;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\InstituteUser;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class BatchResource extends Resource
{
    protected static ?string $model = Batch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Institute setup';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('course_id')->label('Course')->required()->options(fn () => self::courseOptions()),
            Select::make('academic_session_id')->label('Session')->required()
                ->options(fn () => AcademicSession::query()->orderByDesc('starts_on')->pluck('name', 'id'))
                ->default(fn () => AcademicSession::current()?->id),
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('code')->required()->maxLength(30)
                ->unique(table: 'batches', column: 'code', ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('institute_id', auth('institute')->user()?->institute_id)),
            TimePicker::make('start_time')->seconds(false),
            TimePicker::make('end_time')->seconds(false)->after('start_time'),
            CheckboxList::make('days')->columns(7)->columnSpanFull()
                ->options(['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun']),
            TextInput::make('capacity')->integer()->minValue(1)->maxValue(500),
            Select::make('teacher_user_id')->label('Teacher')
                ->options(fn () => InstituteUser::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            DatePicker::make('starts_on'),
            DatePicker::make('ends_on')->afterOrEqual('starts_on'),
            Select::make('status')->options(BatchStatus::class)->default(BatchStatus::Upcoming)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['course', 'teacher', 'academicSession']))
            ->columns([
                TextColumn::make('code')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('course.code')->label('Course'),
                TextColumn::make('academicSession.name')->label('Session'),
                TextColumn::make('timing')->state(fn (Batch $r) => $r->start_time ? substr($r->start_time, 0, 5).'–'.substr((string) $r->end_time, 0, 5) : null)->placeholder('—'),
                TextColumn::make('teacher.name')->label('Teacher')->placeholder('—'),
                TextColumn::make('capacity')->placeholder('—'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([SelectFilter::make('status')->options(BatchStatus::class)])
            ->recordActions([EditAction::make()]);
    }

    /** @return array<int, string> Courses HQ has authorized for the logged-in institute. */
    public static function courseOptions(): array
    {
        return auth('institute')->user()?->institute?->authorizedCourses()->orderBy('code')->get()
            ->mapWithKeys(fn (Course $c) => [$c->id => "{$c->code} — {$c->localized('name')}"])->all() ?? [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBatches::route('/'),
            'create' => CreateBatch::route('/create'),
            'edit' => EditBatch::route('/{record}/edit'),
        ];
    }
}
