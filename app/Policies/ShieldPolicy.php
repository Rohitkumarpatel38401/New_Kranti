<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-panel policy driven by Shield permission keys such as "ViewAny:Course".
 * Only HQ admins can pass; super admins pass earlier through Gate::before.
 */
abstract class ShieldPolicy
{
    protected static string $subject;

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'ViewAny');
    }

    public function view(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'View');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->allows($user, 'Create');
    }

    public function update(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'Update');
    }

    public function delete(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'Delete');
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'DeleteAny');
    }

    public function restore(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'Restore');
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'RestoreAny');
    }

    public function forceDelete(Authenticatable $user, Model $model): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function replicate(Authenticatable $user, Model $model): bool
    {
        return false;
    }

    public function reorder(Authenticatable $user): bool
    {
        return false;
    }

    protected function allows(Authenticatable $user, string $action): bool
    {
        return $user instanceof Admin && $user->can($action.':'.static::$subject);
    }
}
