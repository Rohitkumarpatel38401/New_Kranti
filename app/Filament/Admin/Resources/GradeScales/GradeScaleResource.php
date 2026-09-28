<?php

namespace App\Filament\Admin\Resources\GradeScales;

use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\GradeScales\Pages\CreateGradeScale;
use App\Filament\Admin\Resources\GradeScales\Pages\EditGradeScale;
use App\Filament\Admin\Resources\GradeScales\Pages\ListGradeScales;
use App\Filament\Shared\LocalizedFields;
use App\Models\GradeScale;
use App\Services\GradeScaleService;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class GradeScaleResource extends Resource
{
    protected static ?string $model = GradeScale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('grade')->required()->maxLength(5)->unique(ignoreRecord: true),
            TextInput::make('min_percent')->label('From %')->required()->numeric()->minValue(0)->maxValue(100)
                ->rules([fn (Get $get, ?GradeScale $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record) {
                    try {
                        app(GradeScaleService::class)->assertNoOverlap((float) $value, (float) $get('max_percent'), $record?->id);
                    } catch (DomainRuleException $e) {
                        $fail($e->getMessage());
                    }
                }]),
            TextInput::make('max_percent')->label('To %')->required()->numeric()->minValue(0)->maxValue(100),
            ...LocalizedFields::make('remark', 'Remark', required: false),
            TextInput::make('sort_order')->integer()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('min_percent', 'desc')
            ->columns([
                TextColumn::make('grade')->weight('bold'),
                TextColumn::make('min_percent')->label('From %'),
                TextColumn::make('max_percent')->label('To %'),
                TextColumn::make('remark')->state(fn (GradeScale $record) => $record->localized('remark')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGradeScales::route('/'),
            'create' => CreateGradeScale::route('/create'),
            'edit' => EditGradeScale::route('/{record}/edit'),
        ];
    }
}
