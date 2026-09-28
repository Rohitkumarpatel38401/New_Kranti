<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Filament\Admin\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles as ShieldListRoles;

class ListRoles extends ShieldListRoles
{
    protected static string $resource = RoleResource::class;
}
