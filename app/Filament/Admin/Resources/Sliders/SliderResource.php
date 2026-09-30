<?php

namespace App\Filament\Admin\Resources\Sliders;

use App\Filament\Admin\Resources\Sliders\Pages\ManageSliders;
use App\Filament\Shared\LocalizedFields;
use App\Models\Slider;
use App\Support\SafeUrl;
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

class SliderResource extends Resource
{
    protected static ?string $model = Slider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Homepage slides';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            ...LocalizedFields::make('label', 'Small label', required: false),
            ...LocalizedFields::make('title', 'Heading'),
            ...LocalizedFields::make('subtitle', 'Text', textarea: true, required: false),
            ...LocalizedFields::make('button_text', 'Button text', required: false),
            TextInput::make('button_url')->label('Button link')->maxLength(255)
                ->regex(SafeUrl::PATTERN)->helperText('A full https:// link, or a site path such as /courses')
                ->validationMessages(['regex' => 'Use a link starting with https:// or /']),
            FileUpload::make('image_path')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('sliders')->maxSize(3072)->columnSpanFull(),
            TextInput::make('sort_order')->integer()->default(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_path')->disk('public')->label('Image'),
                TextColumn::make('title')->state(fn ($record) => $record->localized('title')),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSliders::route('/')];
    }
}
