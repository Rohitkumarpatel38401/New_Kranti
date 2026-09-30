<?php

namespace App\Policies;

use App\Enums\ResultStatus;
use App\Models\Admin;
use App\Models\Enrollment;
use App\Models\Student;
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

    /** A student sees only their own published results. */
    protected function studentMayView(Student $student, Model $model): bool
    {
        return $model->status === ResultStatus::Published
            && Enrollment::query()->withoutGlobalScopes()->whereKey($model->enrollment_id)->value('student_id') === $student->id;
    }
}
