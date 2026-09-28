<?php

namespace App\Filament\Admin\Resources\CourseCategories;

use App\Filament\Admin\Resources\CourseCategories\Pages\ManageCourseCategories;
use App\Filament\Shared\GuardedDeleteAction;
use App\Filament\Shared\LocalizedFields;
use App\Models\CourseCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CourseCategoryResource extends Resource
{
    protected static ?string $model = CourseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'course category';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            ...LocalizedFields::make('name', 'Name'),
            TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('sort_order')->integer()->default(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->state(fn (CourseCategory $record) => $record->localized('name')),
                TextColumn::make('slug'),
                TextColumn::make('courses_count')->counts('courses')->label('Courses'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), GuardedDeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCourseCategories::route('/')];
    }
}
