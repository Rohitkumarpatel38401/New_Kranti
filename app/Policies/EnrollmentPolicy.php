<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class EnrollmentPolicy extends InstituteScopedPolicy
{
    protected static string $subject = 'Enrollment';

    protected static string $managePermission = 'submit_enrollment';

    /** HQ approval or rejection of a submitted enrollment. */
    public function review(Authenticatable $user, Model $model): bool
    {
        return $user instanceof Admin && $user->can('approve_enrollment');
    }
}
