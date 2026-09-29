<?php

namespace App\Filament\Institute\Resources\Students\RelationManagers;

use App\Enums\StudentDocumentType;
use App\Enums\VerificationStatus;
use App\Models\StudentDocument;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Copies of Aadhaar, marksheets, etc. Private disk; only people who can see the student can download.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth('institute')->user()?->can('view', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->options(StudentDocumentType::class)->required(),
            FileUpload::make('path')->label('File')->required()
                ->disk('local')->visibility('private')
                ->directory(fn () => 'students/'.$this->getOwnerRecord()->getKey().'/documents')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(5120),
        ]);
    }

    public function table(Table $table): Table
    {
        $canManage = fn (): bool => (bool) auth('institute')->user()?->can('update', $this->getOwnerRecord());

        return $table
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('verification_status')->label('Status')->badge(),
                TextColumn::make('created_at')->label('Uploaded')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()->label('Upload document')->authorize($canManage)
                    ->mutateDataUsing(fn (array $data) => [
                        ...$data,
                        'disk' => 'local',
                        'mime_type' => Storage::disk('local')->mimeType($data['path']) ?: null,
                        'size' => Storage::disk('local')->size($data['path']),
                        'verification_status' => VerificationStatus::Pending,
                    ]),
            ])
            ->recordActions([
                Action::make('download')->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (StudentDocument $record) => Storage::disk($record->disk)->download($record->path)),
            ]);
    }
}
