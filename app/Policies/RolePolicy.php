<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Role;

/**
 * Role editing lets a user grant any permission, so it needs manage_admins.
 * Super Admin passes through Gate::before.
 */
class RolePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $this->canManage($user);
    }

    public function view(Authenticatable $user, Role $role): bool
    {
        return $this->canManage($user);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->canManage($user);
    }

    public function update(Authenticatable $user, Role $role): bool
    {
        return $this->canManage($user);
    }

    public function delete(Authenticatable $user, Role $role): bool
    {
        return $this->canManage($user);
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $this->canManage($user);
    }

    public function restore(Authenticatable $user, Role $role): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user, Role $role): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function replicate(Authenticatable $user, Role $role): bool
    {
        return false;
    }

    public function reorder(Authenticatable $user): bool
    {
        return false;
    }

    private function canManage(Authenticatable $user): bool
    {
        return $user instanceof Admin && $user->can('manage_admins');
    }
}
