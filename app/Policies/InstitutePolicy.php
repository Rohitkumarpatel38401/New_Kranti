<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Institute;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * HQ access to institutes, using Shield's resource permission keys (e.g. ViewAny:Institute).
 */
class InstitutePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'ViewAny:Institute');
    }

    public function view(Authenticatable $user, Institute $institute): bool
    {
        return $this->allows($user, 'View:Institute');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->allows($user, 'Create:Institute');
    }

    public function update(Authenticatable $user, Institute $institute): bool
    {
        return $this->allows($user, 'Update:Institute');
    }

    public function delete(Authenticatable $user, Institute $institute): bool
    {
        return ! $institute->is_main && $this->allows($user, 'Delete:Institute');
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'DeleteAny:Institute');
    }

    public function restore(Authenticatable $user, Institute $institute): bool
    {
        return $this->allows($user, 'Restore:Institute');
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'RestoreAny:Institute');
    }

    public function forceDelete(Authenticatable $user, Institute $institute): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function replicate(Authenticatable $user, Institute $institute): bool
    {
        return false;
    }

    public function reorder(Authenticatable $user): bool
    {
        return false;
    }

    private function allows(Authenticatable $user, string $permission): bool
    {
        return $user instanceof Admin && $user->can($permission);
    }
}
