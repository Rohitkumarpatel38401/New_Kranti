<?php

namespace App\Filament\Admin\Resources\FacultyMembers;

use App\Filament\Admin\Resources\FacultyMembers\Pages\ManageFacultyMembers;
use App\Filament\Shared\LocalizedFields;
use App\Models\FacultyMember;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FacultyMemberResource extends Resource
{
    protected static ?string $model = FacultyMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Faculty';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            ...LocalizedFields::make('designation', 'Subject / designation', required: false),
            TextInput::make('qualification')->maxLength(255),
            TextInput::make('experience_years')->integer()->minValue(0)->maxValue(60),
            FileUpload::make('photo_path')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('faculty')->maxSize(3072)->columnSpanFull(),
            TextInput::make('sort_order')->integer()->default(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('photo_path')->disk('public')->label('Photo')->circular(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('designation')->state(fn ($record) => $record->localized('designation')),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFacultyMembers::route('/')];
    }
}
