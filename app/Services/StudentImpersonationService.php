<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\InstituteUser;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;

/**
 * "Login as student": HQ (impersonate_student) for any student, an institute user (impersonate_student)
 * for their own institute's students. The impersonator's own session stays signed in; leaving logs only
 * the student guard out and returns them to their panel.
 */
class StudentImpersonationService
{
    public const SESSION_KEY = 'impersonating_student_by';

    public function start(Admin|InstituteUser $by, Student $student): void
    {
        $allowed = $by instanceof Admin
            ? $by->is_active && $by->can('impersonate_student')
            : $by->is_active && $by->can('impersonate_student') && $by->institute_id === $student->institute_id;

        abort_unless($allowed, 403);
        abort_unless($student->portalOpen(), 422, 'This student cannot use the portal yet (login closed, no approved course, or institute not active).');

        Auth::guard('student')->login($student);
        session()->put(self::SESSION_KEY, ['type' => $by instanceof Admin ? 'admin' : 'institute', 'id' => $by->getKey()]);

        activity()->causedBy($by)->performedOn($student)->event('student-impersonation.started')
            ->log("Logged in as student {$student->registration_no}");
    }

    /** @return string where to send the impersonator back to */
    public function stop(): string
    {
        $by = $this->impersonator();
        abort_if($by === null, 403);

        $student = Auth::guard('student')->user();
        Auth::guard('student')->logoutCurrentDevice();
        session()->forget(self::SESSION_KEY);

        $log = activity()->causedBy($by)->event('student-impersonation.stopped');

        if ($student) {
            $log->performedOn($student);
        }

        $log->log('Stopped viewing as student');

        return $by instanceof Admin ? '/admin/students' : '/institute/students';
    }

    public function impersonator(): Admin|InstituteUser|null
    {
        $by = session(self::SESSION_KEY);

        return match ($by['type'] ?? null) {
            'admin' => Admin::find($by['id']),
            'institute' => InstituteUser::find($by['id']),
            default => null,
        };
    }

    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }
}
