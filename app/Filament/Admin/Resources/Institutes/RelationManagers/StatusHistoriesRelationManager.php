<?php

namespace App\Filament\Admin\Resources\Institutes\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Status history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('changed_at', 'desc')
            ->columns([
                TextColumn::make('changed_at')->label('When')->dateTime(),
                TextColumn::make('from_status')->label('From')->badge()->placeholder('—'),
                TextColumn::make('to_status')->label('To')->badge(),
                TextColumn::make('reason')->wrap(),
                TextColumn::make('changedBy.name')->label('By')->placeholder('System'),
            ]);
    }
}
