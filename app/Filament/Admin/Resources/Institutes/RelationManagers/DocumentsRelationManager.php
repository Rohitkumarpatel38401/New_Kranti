<?php

namespace App\Filament\Admin\Resources\Institutes\RelationManagers;

use App\Enums\DocumentType;
use App\Enums\VerificationStatus;
use App\Models\InstituteDocument;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    // Documents are uploaded and verified from the institute's view page as well.
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->options(DocumentType::class)->required(),
            TextInput::make('number')->label('Document number')->maxLength(100),
            FileUpload::make('path')
                ->label('File')
                ->disk('local')
                ->visibility('private')
                ->directory(fn () => "institutes/{$this->getOwnerRecord()->getKey()}/documents")
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(5120)
                ->required()
                ->columnSpanFull(),
            TextInput::make('remarks')->maxLength(255)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('number')->placeholder('—'),
                TextColumn::make('verification_status')->label('Status')->badge()
                    ->color(fn (VerificationStatus $state) => match ($state) {
                        VerificationStatus::Verified => 'success',
                        VerificationStatus::Rejected => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('verifiedBy.name')->label('Verified by')->placeholder('—'),
                TextColumn::make('created_at')->label('Uploaded')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $disk = Storage::disk('local');

                        return [
                            ...$data,
                            'disk' => 'local',
                            'mime_type' => $disk->mimeType($data['path']) ?: null,
                            'size' => $disk->size($data['path']),
                            'verification_status' => VerificationStatus::Pending,
                        ];
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (InstituteDocument $record) => Storage::disk($record->disk)->download($record->path)),
                Action::make('verify')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (InstituteDocument $record) => $record->verification_status !== VerificationStatus::Verified
                        && auth('admin')->user()?->can('verify', $record))
                    ->action(fn (InstituteDocument $record) => $this->review($record, VerificationStatus::Verified, $record->remarks)),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (InstituteDocument $record) => $record->verification_status !== VerificationStatus::Rejected
                        && auth('admin')->user()?->can('verify', $record))
                    ->schema([TextInput::make('remarks')->required()->maxLength(255)])
                    ->action(fn (InstituteDocument $record, array $data) => $this->review($record, VerificationStatus::Rejected, $data['remarks'])),
                DeleteAction::make(),
            ]);
    }

    private function review(InstituteDocument $document, VerificationStatus $status, ?string $remarks): void
    {
        $document->update([
            'verification_status' => $status,
            'verified_by_admin_id' => auth('admin')->id(),
            'verified_at' => now(),
            'remarks' => $remarks,
        ]);
    }
}
