<?php

namespace App\Filament\Institute\Resources\Staff;

use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Resources\Staff\Pages\CreateStaff;
use App\Filament\Institute\Resources\Staff\Pages\EditStaff;
use App\Filament\Institute\Resources\Staff\Pages\ListStaff;
use App\Models\InstituteUser;
use App\Services\StaffService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaffResource extends Resource
{
    protected static ?string $model = InstituteUser::class;

    protected static ?string $slug = 'staff';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->required()->email()->maxLength(255)
                // Login emails are unique across every institute, including removed accounts.
                ->unique(table: 'institute_users', ignoreRecord: true),
            TextInput::make('phone')->tel()->maxLength(20),
            TextInput::make('password')->password()->revealable()->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
            Select::make('role')->required()
                ->options(fn () => collect(StaffService::assignableRoles())->mapWithKeys(fn ($r) => [$r => ucfirst($r)])->all()),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('roles.name')->label('Role')->badge(),
                IconColumn::make('is_owner')->label('Owner')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('last_login_at')->dateTime()->placeholder('Never'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('remove')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (InstituteUser $record) => auth('institute')->user()?->can('delete', $record))
                    ->action(function (InstituteUser $record) {
                        try {
                            app(StaffService::class)->delete($record, auth('institute')->user());
                            Notification::make()->success()->title('Staff member removed')->send();
                        } catch (DomainRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}
