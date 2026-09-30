<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;

/**
 * Student portal account: the forced first-login password change (the initial password is the
 * date of birth or registration number, so it must be replaced).
 */
class StudentAccountService
{
    public const MIN_LENGTH = 8;

    public function changePassword(Student $student, string $current, string $new): void
    {
        if (! Hash::check($current, (string) $student->password)) {
            throw new DomainRuleException(__('portal.password.wrong_current'));
        }

        if (mb_strlen($new) < self::MIN_LENGTH) {
            throw new DomainRuleException(__('portal.password.too_short', ['min' => self::MIN_LENGTH]));
        }

        if ($new === $current || in_array($new, $this->defaults($student), true)) {
            throw new DomainRuleException(__('portal.password.same'));
        }

        $student->forceFill(['password' => $new, 'must_change_password' => false])->save();
    }

    /** The passwords the portal is opened with (EnrollmentService::openStudentLogin). */
    private function defaults(Student $student): array
    {
        return array_filter([$student->personal?->dob?->format('dmY'), $student->registration_no]);
    }
}
