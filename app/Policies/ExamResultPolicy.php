<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class ExamResultPolicy extends InstituteScopedPolicy
{
    protected static string $subject = 'ExamResult';

    protected static string $managePermission = 'enter_marks';

    /** HQ publishing or returning a submitted result. */
    public function review(Authenticatable $user, Model $model): bool
    {
        return $user instanceof Admin && $user->can('publish_result');
    }
}
