<?php

namespace App\Filament\Admin\Resources\Institutes;

use App\Enums\InstituteStatus;
use App\Filament\Admin\Resources\Institutes\Pages\ListInstitutes;
use App\Models\Institute;
use App\Services\ImpersonationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

class InstituteResource extends Resource
{
    protected static ?string $model = Institute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('is_main')->label('Main')->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (InstituteStatus $state) => $state->label())
                    ->color(fn (InstituteStatus $state) => match ($state) {
                        InstituteStatus::Active => 'success',
                        InstituteStatus::Pending => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('phone'),
                TextColumn::make('email')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(InstituteStatus::cases())->mapWithKeys(fn (InstituteStatus $case) => [$case->value => $case->label()])->all()
                ),
            ])
            ->recordActions([
                Action::make('impersonate')
                    ->label('Login as Institute')
                    ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                    ->color('gray')
                    ->visible(fn (Institute $record): bool => (bool) auth('admin')->user()?->can('impersonate_institute')
                        && $record->isActive()
                        && app(ImpersonationService::class)->ownerOf($record) !== null)
                    ->url(fn (Institute $record): string => URL::temporarySignedRoute(
                        'impersonation.start', now()->addMinutes(5), ['institute' => $record]
                    ))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstitutes::route('/'),
        ];
    }
}
