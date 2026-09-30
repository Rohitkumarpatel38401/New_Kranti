<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\WalletSource;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Support\PanelNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment lifecycle (spec §6.3). Every change locks the enrollment, checks the transition,
 * moves money through WalletService when needed, and writes a history row.
 */
class EnrollmentService
{
    public function __construct(
        private WalletService $wallets,
        private NumberSequenceService $sequences,
    ) {}

    public function createDraft(Student $student, Course $course, ?Batch $batch, AcademicSession $session, string $courseFee, string $discount, InstituteUser|Admin $by, ?string $admissionDate = null): Enrollment
    {
        if ($by instanceof InstituteUser && $by->institute_id !== $student->institute_id) {
            throw new DomainRuleException('This student belongs to another institute.');
        }

        $institute = $student->institute;

        if (! $institute->isAuthorizedFor($course)) {
            throw new DomainRuleException("{$institute->name} is not authorized to teach {$course->code}.");
        }

        if ($batch && ($batch->institute_id !== $student->institute_id || $batch->course_id !== $course->id)) {
            throw new DomainRuleException('Choose a batch of this institute for the same course.');
        }

        $fee = WalletService::toPaise($courseFee);
        $off = WalletService::toPaise($discount === '' ? '0' : $discount);

        if ($fee < 0 || $off < 0) {
            throw new DomainRuleException('Enter the course fee and discount in rupees with at most two decimals.');
        }

        if ($off > $fee) {
            throw new DomainRuleException('The discount cannot be more than the course fee.');
        }

        return DB::transaction(function () use ($student, $course, $batch, $session, $fee, $off, $by, $admissionDate) {
            $this->assertNoOtherOpenEnrollment($student, $course);

            $enrollment = Enrollment::create([
                'institute_id' => $student->institute_id,
                'student_id' => $student->id,
                'course_id' => $course->id,
                'batch_id' => $batch?->id,
                'academic_session_id' => $session->id,
                'admission_date' => $admissionDate ?? now()->toDateString(),
                'course_fee' => WalletService::fromPaise($fee),
                'discount' => WalletService::fromPaise($off),
                'net_fee' => WalletService::fromPaise($fee - $off),
                'status' => EnrollmentStatus::Draft,
            ]);

            $this->record($enrollment, null, EnrollmentStatus::Draft, null, $by);

            return $enrollment;
        });
    }

    /**
     * One-step admission: new student + enrollment, submitted at once. HQ's own admissions are approved
     * immediately (active, enrollment number, portal login); an institute's wait for HQ approval.
     * One transaction: a short wallet or any rule failure saves nothing.
     *
     * @param  array<int, array<string, mixed>>  $guardians
     */
    public function admitDirectly(array $student, array $personal, array $guardians, array $address, Course $course, ?Batch $batch, AcademicSession $session, string $courseFee, string $discount, InstituteUser|Admin $by, ?Institute $institute = null, ?string $admissionDate = null): Enrollment
    {
        return DB::transaction(function () use ($student, $personal, $guardians, $address, $course, $batch, $session, $courseFee, $discount, $by, $institute, $admissionDate) {
            $record = app(StudentService::class)->create($student, $personal, $guardians, $address, [], $by, $institute);

            $enrollment = $this->createDraft($record, $course, $batch, $session, $courseFee, $discount, $by, $admissionDate);
            $this->submit($enrollment, $by);

            if ($by instanceof Admin) {
                $this->approve($enrollment->fresh(), $by);
            }

            return $enrollment->fresh(['student']);
        });
    }

    public function submit(Enrollment $enrollment, InstituteUser|Admin $by): void
    {
        $this->transition($enrollment, EnrollmentStatus::PendingApproval, $by, null, function (Enrollment $locked) use ($by) {
            $institute = $locked->student->institute;
            $fee = $institute->hqFeeFor($locked->course);
            $debit = null;

            if (! $institute->wallet_exempt && WalletService::toPaise($fee) > 0) {
                $debit = $this->wallets->debit($institute, $fee, WalletSource::EnrollmentFee, $locked,
                    "HQ fee: {$locked->course->code} for {$locked->student->registration_no}", $by);
            }

            $locked->fill([
                'hq_fee_snapshot' => $fee,
                'debit_transaction_id' => $debit?->id,
                'submitted_at' => now(),
            ]);
        });

        PanelNotifier::admins('approve_enrollment', 'Enrollment awaiting approval', $this->describe($enrollment), url('/admin/enrollments'), 'heroicon-o-academic-cap');
    }

    public function approve(Enrollment $enrollment, Admin $by): void
    {
        $this->transition($enrollment, EnrollmentStatus::Active, $by, null, function (Enrollment $locked) use ($by) {
            $year = now()->year;

            $locked->fill([
                'enrollment_no' => sprintf('KCTI/%d/%06d', $year, $this->sequences->next("enrollment:{$year}")),
                'approved_by_admin_id' => $by->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->openStudentLogin($locked->student);
        });

        $enrollment->refresh();
        PanelNotifier::institute($enrollment->institute_id, 'submit_enrollment', 'Enrollment approved',
            $this->describe($enrollment)." · {$enrollment->enrollment_no}", url('/institute/enrollments'), 'heroicon-o-check-circle');
    }

    public function reject(Enrollment $enrollment, string $reason, Admin $by): void
    {
        if (trim($reason) === '') {
            throw new DomainRuleException('A reason is required to reject an enrollment.');
        }

        $this->transition($enrollment, EnrollmentStatus::Rejected, $by, $reason, function (Enrollment $locked) use ($reason, $by) {
            if ($locked->debit_transaction_id) {
                $this->wallets->credit($locked->student->institute, (string) $locked->hq_fee_snapshot, WalletSource::EnrollmentRefund, $locked,
                    "Refund: {$locked->course->code} for {$locked->student->registration_no} rejected", $by);
            }

            $locked->rejection_reason = $reason;
        });

        PanelNotifier::institute($enrollment->institute_id, 'submit_enrollment', 'Enrollment rejected',
            $this->describe($enrollment)." · {$reason}", url('/institute/enrollments'), 'heroicon-o-x-circle');
    }

    public function reopen(Enrollment $enrollment, InstituteUser|Admin $by): void
    {
        $this->transition($enrollment, EnrollmentStatus::Draft, $by, null, function (Enrollment $locked) {
            // A new draft may have been created while this one was rejected.
            $this->assertNoOtherOpenEnrollment($locked->student()->withoutGlobalScopes()->firstOrFail(), $locked->course, $locked->id);
            $locked->fill(['hq_fee_snapshot' => null, 'debit_transaction_id' => null, 'submitted_at' => null]);
        });
    }

    public function cancel(Enrollment $enrollment, InstituteUser|Admin $by, ?string $reason = null): void
    {
        $this->transition($enrollment, EnrollmentStatus::Cancelled, $by, $reason);
    }

    public function complete(Enrollment $enrollment, Model $by): void
    {
        $this->transition($enrollment, EnrollmentStatus::Completed, $by, null, fn (Enrollment $locked) => $locked->completed_on = now()->toDateString());
    }

    public function drop(Enrollment $enrollment, string $reason, InstituteUser|Admin $by): void
    {
        if (trim($reason) === '') {
            throw new DomainRuleException('A reason is required to mark an enrollment as dropped.');
        }

        $this->transition($enrollment, EnrollmentStatus::Dropped, $by, $reason);
    }

    private function transition(Enrollment $enrollment, EnrollmentStatus $to, Model $by, ?string $reason = null, ?callable $apply = null): void
    {
        DB::transaction(function () use ($enrollment, $to, $by, $reason, $apply) {
            $locked = Enrollment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($enrollment->id);
            $from = $locked->status;

            if ($by instanceof InstituteUser && $by->institute_id !== $locked->institute_id) {
                throw new DomainRuleException('This enrollment belongs to another institute.');
            }

            if (! $from->canMoveTo($to)) {
                throw new DomainRuleException("An enrollment that is {$from->label()} cannot become {$to->label()}.");
            }

            if ($apply) {
                $apply($locked);
            }

            $locked->status = $to;
            $locked->save();

            $this->record($locked, $from, $to, $reason, $by);
        });

        $enrollment->refresh();
    }

    /** Locks the student so two requests cannot both pass this check. */
    private function assertNoOtherOpenEnrollment(Student $student, Course $course, ?int $ignoreEnrollmentId = null): void
    {
        Student::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($student->id);

        $open = Enrollment::query()->withoutGlobalScopes()
            ->where('student_id', $student->id)->where('course_id', $course->id)
            ->whereIn('status', EnrollmentStatus::open())
            ->when($ignoreEnrollmentId, fn ($q) => $q->whereKeyNot($ignoreEnrollmentId))
            ->exists();

        if ($open) {
            throw new DomainRuleException("{$student->full_name} is already enrolled in {$course->code}.");
        }
    }

    private function openStudentLogin(Student $student): void
    {
        if ($student->can_login) {
            return;
        }

        $dob = $student->personal?->dob;

        $student->forceFill([
            'password' => $dob ? $dob->format('dmY') : $student->registration_no,
            'must_change_password' => true,
            'can_login' => true,
        ])->save();
    }

    private function record(Enrollment $enrollment, ?EnrollmentStatus $from, EnrollmentStatus $to, ?string $reason, Model $by): void
    {
        $enrollment->histories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'actor_type' => $by->getMorphClass(),
            'actor_id' => $by->getKey(),
            'changed_at' => now(),
        ]);
    }

    /** "Student Name — COURSE (Institute)" for notifications. */
    private function describe(Enrollment $enrollment): string
    {
        $student = Student::query()->withoutGlobalScopes()->find($enrollment->student_id);
        $course = Course::query()->find($enrollment->course_id);
        $institute = Institute::query()->find($enrollment->institute_id);

        return "{$student?->full_name} — {$course?->code} ({$institute?->name})";
    }
}
