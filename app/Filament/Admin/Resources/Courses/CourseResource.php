<?php

namespace App\Filament\Admin\Resources\Courses;

use App\Enums\DurationUnit;
use App\Filament\Admin\Resources\Courses\Pages\CreateCourse;
use App\Filament\Admin\Resources\Courses\Pages\EditCourse;
use App\Filament\Admin\Resources\Courses\Pages\ListCourses;
use App\Filament\Admin\Resources\Courses\RelationManagers\SubjectsRelationManager;
use App\Filament\Shared\LocalizedFields;
use App\Models\Course;
use App\Models\CourseCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Course')->columns(2)->schema([
                Select::make('course_category_id')->label('Category')->required()
                    ->options(fn () => CourseCategory::query()->orderBy('sort_order')->get()
                        ->mapWithKeys(fn (CourseCategory $c) => [$c->id => $c->localized('name')])),
                TextInput::make('code')->required()->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state) => $state === null ? null : strtoupper(trim($state))),
                ...LocalizedFields::make('name', 'Name'),
                TextInput::make('duration_value')->label('Duration')->required()->integer()->minValue(1),
                Select::make('duration_unit')->options(DurationUnit::class)->required()->default(DurationUnit::Months),
                TextInput::make('total_hours')->integer()->minValue(0),
                TextInput::make('sort_order')->integer()->default(0),
            ]),
            Section::make('Fees')->columns(2)->schema([
                TextInput::make('hq_registration_fee')->label('HQ registration fee (₹)')->required()->numeric()->minValue(0)
                    ->helperText('Debited from the franchise wallet for each enrollment.'),
                TextInput::make('suggested_fee')->label('Suggested course fee (₹)')->numeric()->minValue(0),
            ]),
            Section::make('Website')->columns(2)->schema([
                ...LocalizedFields::make('short_description', 'Short description', textarea: true, required: false),
                ...LocalizedFields::make('description', 'Description', textarea: true, required: false),
                ...LocalizedFields::make('eligibility', 'Eligibility', required: false),
                FileUpload::make('thumbnail_path')->label('Thumbnail')->image()->disk('public')->directory('courses')->maxSize(2048),
                Toggle::make('show_on_website')->default(true),
                Toggle::make('is_featured'),
                Toggle::make('is_active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->state(fn (Course $record) => $record->localized('name'))
                    ->searchable(query: fn ($query, string $search) => $query->where('name', 'like', "%{$search}%")),
                TextColumn::make('category.name')->label('Category')
                    ->state(fn (Course $record) => $record->category?->localized('name')),
                TextColumn::make('duration')->state(fn (Course $record) => $record->durationLabel()),
                TextColumn::make('hq_registration_fee')->label('HQ fee')->money('INR'),
                TextColumn::make('course_subjects_count')->counts('courseSubjects')->label('Subjects'),
                IconColumn::make('show_on_website')->label('Website')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('course_category_id')->label('Category')
                    ->options(fn () => CourseCategory::all()->mapWithKeys(fn (CourseCategory $c) => [$c->id => $c->localized('name')])),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [SubjectsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }
}
