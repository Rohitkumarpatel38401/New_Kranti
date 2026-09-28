<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admins see an institute's users read-only (via View:Institute).
 * Institute-side staff management rules are added with the Staff resource.
 */
class InstituteUserPolicy extends ShieldPolicy
{
    protected static string $subject = 'Institute';

    public function viewAny(Authenticatable $user): bool
    {
        return $user instanceof Admin && $this->allows($user, 'View');
    }

    public function view(Authenticatable $user, Model $model): bool
    {
        return $user instanceof Admin && $this->allows($user, 'View');
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, Model $model): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user, Model $model): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }
}
