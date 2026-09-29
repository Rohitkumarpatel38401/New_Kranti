<?php

namespace App\Filament\Admin\Resources\Notices;

use App\Filament\Admin\Resources\Notices\Pages\ManageNotices;
use App\Filament\Shared\LocalizedFields;
use App\Models\Notice;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class NoticeResource extends Resource
{
    protected static ?string $model = Notice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'News & notices';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            ...LocalizedFields::make('title', 'Title'),
            ...LocalizedFields::make('body', 'Details', textarea: true, required: false),
            Select::make('badge')->options(['new' => 'New', 'hot' => 'Hot'])->placeholder('None'),
            DateTimePicker::make('published_at')->label('Publish at')->default(now())->required(),
            Toggle::make('show_in_ticker')->label('Show in scrolling ticker'),
            FileUpload::make('image_path')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('notices')->maxSize(3072)->columnSpanFull(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->state(fn ($record) => $record->localized('title'))->wrap(),
                TextColumn::make('published_at')->dateTime()->sortable(),
                IconColumn::make('show_in_ticker')->boolean()->label('Ticker'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNotices::route('/')];
    }
}
