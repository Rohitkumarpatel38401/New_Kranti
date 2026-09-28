<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * HQ team management needs manage_admins (Super Admin passes through Gate::before).
 */
class AdminPolicy extends ShieldPolicy
{
    protected static string $subject = 'Admin';

    protected function allows(Authenticatable $user, string $action): bool
    {
        return $user instanceof Admin && $user->can('manage_admins');
    }
}
