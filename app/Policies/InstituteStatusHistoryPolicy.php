<?php

namespace App\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Status history is read-only; it is written only by InstituteService.
 */
class InstituteStatusHistoryPolicy extends ShieldPolicy
{
    protected static string $subject = 'Institute';

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'View');
    }

    public function view(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'View');
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
