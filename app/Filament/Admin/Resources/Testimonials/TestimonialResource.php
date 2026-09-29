<?php

namespace App\Filament\Admin\Resources\Testimonials;

use App\Filament\Admin\Resources\Testimonials\Pages\ManageTestimonials;
use App\Filament\Shared\LocalizedFields;
use App\Models\Testimonial;
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

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Alumni & testimonials';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('course_name')->label('Course')->maxLength(100),
            TextInput::make('position')->label('Now working as')->maxLength(100),
            TextInput::make('company')->maxLength(100),
            ...LocalizedFields::make('quote', 'What they say', textarea: true, required: false),
            FileUpload::make('photo_path')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('alumni')->maxSize(3072)->columnSpanFull(),
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
                TextColumn::make('course_name')->label('Course'),
                TextColumn::make('position'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTestimonials::route('/')];
    }
}
