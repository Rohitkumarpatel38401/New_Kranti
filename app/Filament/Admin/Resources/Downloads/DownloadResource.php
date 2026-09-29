<?php

namespace App\Filament\Admin\Resources\Downloads;

use App\Filament\Admin\Resources\Downloads\Pages\ManageDownloads;
use App\Filament\Shared\LocalizedFields;
use App\Models\Download;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DownloadResource extends Resource
{
    protected static ?string $model = Download::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Downloads';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            ...LocalizedFields::make('title', 'Title'),
            TextInput::make('category')->maxLength(50)->datalist(['forms', 'syllabus', 'notices']),
            FileUpload::make('path')->label('File')->disk('public')->directory('downloads')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)->required()->columnSpanFull(),
            TextInput::make('sort_order')->integer()->default(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->state(fn ($record) => $record->localized('title')),
                TextColumn::make('category'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDownloads::route('/')];
    }
}
