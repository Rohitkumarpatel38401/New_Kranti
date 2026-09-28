<?php

namespace App\Filament\Admin\Resources\Admins;

use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Admin\Resources\Admins\Pages\EditAdmin;
use App\Filament\Admin\Resources\Admins\Pages\ListAdmins;
use App\Models\Admin;
use App\Services\AdminTeamService;
use App\Support\Permissions\AdminPermissions;
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
use Spatie\Permission\Models\Role;
use UnitEnum;

class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'HQ Team';

    protected static ?string $navigationLabel = 'Team members';

    protected static ?string $modelLabel = 'team member';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->required()->email()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('phone')->tel()->maxLength(20),
            TextInput::make('password')->password()->revealable()->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
            Select::make('roles')->multiple()->required()
                ->options(fn () => self::roleOptions()),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->badge()->label('Roles'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('last_login_at')->dateTime()->placeholder('Never'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('remove')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Admin $record) => auth('admin')->user()?->can('delete', $record))
                    ->action(function (Admin $record) {
                        try {
                            app(AdminTeamService::class)->delete($record, auth('admin')->user());
                            Notification::make()->success()->title('Team member removed')->send();
                        } catch (DomainRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    /** @return array<string, string> */
    public static function roleOptions(): array
    {
        $isSuper = (bool) auth('admin')->user()?->hasRole(AdminPermissions::SUPER_ADMIN);

        return Role::query()->where('guard_name', 'admin')
            ->when(! $isSuper, fn ($query) => $query->where('name', '!=', AdminPermissions::SUPER_ADMIN))
            ->orderBy('name')->pluck('name', 'name')
            ->map(fn (string $name) => str($name)->replace('_', ' ')->title()->toString())
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmins::route('/'),
            'create' => CreateAdmin::route('/create'),
            'edit' => EditAdmin::route('/{record}/edit'),
        ];
    }
}
