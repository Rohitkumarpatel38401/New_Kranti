<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Institute documents follow the institute's permissions: view to see, update to change.
 */
class InstituteDocumentPolicy extends ShieldPolicy
{
    protected static string $subject = 'Institute';

    public function view(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'View');
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'View');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->allows($user, 'Update');
    }

    public function delete(Authenticatable $user, Model $model): bool
    {
        return $this->allows($user, 'Update');
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'Update');
    }

    public function verify(Authenticatable $user, Model $model): bool
    {
        return $user instanceof Admin && $this->allows($user, 'Update');
    }
}
