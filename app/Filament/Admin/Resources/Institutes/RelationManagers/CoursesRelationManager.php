<?php

namespace App\Filament\Admin\Resources\Institutes\RelationManagers;

use App\Models\Course;
use Closure;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Which HQ courses a franchise may teach. Governed by the institute's permissions, not the course's.
 */
class CoursesRelationManager extends RelationManager
{
    protected static string $relationship = 'courses';

    protected static ?string $title = 'Authorized courses';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth('admin')->user()?->can('view', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->pivotFields());
    }

    public function table(Table $table): Table
    {
        $canManage = fn (): bool => (bool) auth('admin')->user()?->can('update', $this->getOwnerRecord());

        return $table
            ->columns([
                TextColumn::make('code'),
                TextColumn::make('name')->state(fn (Course $record) => $record->localized('name')),
                TextColumn::make('hq_registration_fee')->label('Standard HQ fee')->money('INR'),
                TextColumn::make('pivot.hq_fee_override')->label('Fee for this institute')->money('INR')->placeholder('Standard'),
                IconColumn::make('pivot.is_active')->label('Active')->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Authorize course')
                    ->authorize($canManage)
                    ->recordSelect(fn (Select $select) => $select->searchable()
                        ->getOptionLabelFromRecordUsing(fn (Course $record) => "{$record->code} — {$record->localized('name')}")
                        // Searchable selects do not validate against their options, so check duplicates here.
                        ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
                            if ($this->getOwnerRecord()->courses()->whereKey($value)->exists()) {
                                $fail('This course is already authorized for the institute.');
                            }
                        }]))
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('courses.is_active', true))
                    ->schema(fn (AttachAction $action): array => [$action->getRecordSelect(), ...$this->pivotFields()]),
            ])
            ->recordActions([
                EditAction::make()->authorize($canManage),
                DetachAction::make()->label('Remove')->authorize($canManage),
            ]);
    }

    private function pivotFields(): array
    {
        return [
            TextInput::make('hq_fee_override')->label('HQ fee for this institute (₹)')->numeric()->minValue(0)
                ->helperText('Leave empty to use the course\'s standard HQ fee.'),
            Toggle::make('is_active')->label('Active')->default(true),
        ];
    }
}
