<?php

namespace App\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Course subjects are part of a course: viewing needs View:Course, changes need Update:Course.
 */
class CourseSubjectPolicy extends ShieldPolicy
{
    protected static string $subject = 'Course';

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
}
