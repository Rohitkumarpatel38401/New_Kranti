<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\InstituteUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admins see an institute's users read-only (via View:Institute).
 * Inside an institute, everyone sees colleagues; manage_staff is needed to change them,
 * and the owner record is never edited or removed from the staff screen.
 */
class InstituteUserPolicy extends ShieldPolicy
{
    protected static string $subject = 'Institute';

    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof InstituteUser) {
            return $user->is_active;
        }

        return $user instanceof Admin && $this->allows($user, 'View');
    }

    public function view(Authenticatable $user, Model $model): bool
    {
        if ($user instanceof InstituteUser) {
            return $this->sameInstitute($user, $model);
        }

        return $user instanceof Admin && $this->allows($user, 'View');
    }

    public function create(Authenticatable $user): bool
    {
        return $user instanceof InstituteUser && $user->can('manage_staff');
    }

    public function update(Authenticatable $user, Model $model): bool
    {
        return $user instanceof InstituteUser && $user->can('manage_staff')
            && $this->sameInstitute($user, $model) && ! $model->is_owner;
    }

    public function delete(Authenticatable $user, Model $model): bool
    {
        return $this->update($user, $model) && ! $model->is($user);
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

    private function sameInstitute(InstituteUser $user, Model $model): bool
    {
        return $model->institute_id === $user->institute_id;
    }
}
