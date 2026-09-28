<?php

namespace App\Filament\Admin\Resources\AcademicSessions;

use App\Filament\Admin\Resources\AcademicSessions\Pages\CreateAcademicSession;
use App\Filament\Admin\Resources\AcademicSessions\Pages\EditAcademicSession;
use App\Filament\Admin\Resources\AcademicSessions\Pages\ListAcademicSessions;
use App\Models\AcademicSession;
use App\Services\AcademicSessionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AcademicSessionResource extends Resource
{
    protected static ?string $model = AcademicSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(50)->placeholder('2026-27'),
            TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true),
            DatePicker::make('starts_on')->required(),
            DatePicker::make('ends_on')->required()->after('starts_on'),
            Toggle::make('is_current')->label('Current session')
                // There must always be a current session; switch by marking another one current.
                ->disabled(fn (?AcademicSession $record) => (bool) $record?->is_current)
                ->dehydrated(fn (?AcademicSession $record) => ! $record?->is_current)
                ->helperText('Marking this session current unmarks the previous one.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('code'),
                TextColumn::make('starts_on')->date(),
                TextColumn::make('ends_on')->date(),
                IconColumn::make('is_current')->label('Current')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('makeCurrent')
                    ->label('Make current')
                    ->icon(Heroicon::OutlinedStar)
                    ->requiresConfirmation()
                    ->visible(fn (AcademicSession $record) => ! $record->is_current && auth('admin')->user()?->can('update', $record))
                    ->action(fn (AcademicSession $record) => app(AcademicSessionService::class)->makeCurrent($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcademicSessions::route('/'),
            'create' => CreateAcademicSession::route('/create'),
            'edit' => EditAcademicSession::route('/{record}/edit'),
        ];
    }
}
