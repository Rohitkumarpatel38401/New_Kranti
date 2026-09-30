<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Admin;
use App\Models\Student;
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

    /** A student sees their own approved (active or completed) enrollments. */
    protected function studentMayView(Student $student, Model $model): bool
    {
        return $model->student_id === $student->id
            && in_array($model->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true);
    }
}
