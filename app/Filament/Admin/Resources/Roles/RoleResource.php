<?php

namespace App\Filament\Admin\Resources\Roles;

use App\Filament\Admin\Resources\Roles\Pages\CreateRole;
use App\Filament\Admin\Resources\Roles\Pages\EditRole;
use App\Filament\Admin\Resources\Roles\Pages\ListRoles;
use App\Filament\Admin\Resources\Roles\Pages\ViewRole;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shield's role editor limited to HQ (admin guard) roles.
 * Institute roles are defined in App\Support\Permissions\InstitutePermissions.
 */
class RoleResource extends ShieldRoleResource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('guard_name', 'admin');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
