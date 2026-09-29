<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\ResultOutcome;
use App\Enums\ResultStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\GradeScale;
use App\Models\InstituteUser;
use Illuminate\Support\Facades\DB;

/**
 * Marks entry (institute) → submit → publish or return (HQ). Publishing completes the enrollment.
 */
class ResultService
{
    public function __construct(private EnrollmentService $enrollments) {}

    /**
     * @param  array<int, array{theory?: int|string|null, practical?: int|string|null, absent?: bool}>  $marks  course_subject_id => marks
     */
    public function saveMarks(Enrollment $enrollment, array $marks, string $examDate, InstituteUser|Admin $by): ExamResult
    {
        if ($by instanceof InstituteUser && (! $by->can('enter_marks') || $by->institute_id !== $enrollment->institute_id)) {
            throw new DomainRuleException('You are not allowed to enter marks for this student.');
        }

        return DB::transaction(function () use ($enrollment, $marks, $examDate) {
            $locked = Enrollment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($enrollment->id);

            if ($locked->status !== EnrollmentStatus::Active) {
                throw new DomainRuleException('Marks can only be entered for an active (approved) enrollment.');
            }

            $subjects = $locked->course->courseSubjects()->get()->keyBy('id');
            $given = array_map('intval', array_keys($marks));
            sort($given);
            $expected = $subjects->keys()->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($given !== $expected) {
                throw new DomainRuleException('Enter marks for every subject of the course, and only those subjects.');
            }

            $result = ExamResult::query()->withoutGlobalScopes()->where('enrollment_id', $locked->id)->lockForUpdate()->first();

            if ($result && ! $result->isEditable()) {
                throw new DomainRuleException("Marks are {$result->status->label()} and can no longer be changed.");
            }

            $result ??= ExamResult::create(['institute_id' => $locked->institute_id, 'enrollment_id' => $locked->id, 'exam_date' => $examDate]);
            $result->update(['exam_date' => $examDate, 'status' => ResultStatus::Draft]);

            foreach ($marks as $courseSubjectId => $row) {
                $subject = $subjects[(int) $courseSubjectId];
                $absent = (bool) ($row['absent'] ?? false);
                $theory = $absent ? null : $this->score($row['theory'] ?? 0, (int) $subject->theory_max, 'Theory', $subject);
                $practical = $absent ? null : $this->score($row['practical'] ?? 0, (int) $subject->practical_max, 'Practical', $subject);

                $result->marks()->updateOrCreate(['course_subject_id' => $subject->id], [
                    'theory' => $theory,
                    'practical' => $practical,
                    'total' => $absent ? 0 : $theory + $practical,
                    'is_absent' => $absent,
                ]);
            }

            return $result->load('marks');
        });
    }

    public function submit(ExamResult $result, InstituteUser|Admin $by): void
    {
        if ($by instanceof InstituteUser && (! $by->can('submit_result') || $by->institute_id !== $result->institute_id)) {
            throw new DomainRuleException('You are not allowed to submit results.');
        }

        $this->move($result, ResultStatus::Draft, ResultStatus::Submitted, fn (ExamResult $r) => $r->fill([
            'submitted_by_user_id' => $by instanceof InstituteUser ? $by->id : null,
            'submitted_at' => now(),
            'return_remarks' => null,
        ]));
    }

    public function publish(ExamResult $result, Admin $by): void
    {
        $this->move($result, ResultStatus::Submitted, ResultStatus::Published, function (ExamResult $r) use ($by) {
            $marks = $r->marks()->with('courseSubject')->get();
            $obtained = (int) $marks->sum('total');
            $max = (int) $marks->sum(fn ($m) => $m->courseSubject->maxMarks());

            if ($max === 0) {
                throw new DomainRuleException('This course has no marks to publish.');
            }

            // Round first: grade bands are two-decimal and leave 0.01 gaps (e.g. 89.99 → 90).
            $percentage = round($obtained * 100 / $max, 2);
            $passed = $marks->every(fn ($m) => ! $m->is_absent && $m->total >= (int) $m->courseSubject->pass_marks);

            $r->fill([
                'total_obtained' => $obtained,
                'total_max' => $max,
                'percentage' => number_format($percentage, 2, '.', ''),
                'grade' => GradeScale::forPercentage($percentage)?->grade,
                'result' => $passed ? ResultOutcome::Pass : ResultOutcome::Fail,
                'published_by_admin_id' => $by->id,
                'published_at' => now(),
            ]);
        });

        $this->enrollments->complete($result->enrollment()->withoutGlobalScopes()->firstOrFail(), $by);
    }

    public function return(ExamResult $result, string $remarks, Admin $by): void
    {
        if (trim($remarks) === '') {
            throw new DomainRuleException('Tell the institute what to correct.');
        }

        $this->move($result, ResultStatus::Submitted, ResultStatus::Returned, fn (ExamResult $r) => $r->return_remarks = $remarks);
    }

    private function move(ExamResult $result, ResultStatus $from, ResultStatus $to, callable $apply): void
    {
        DB::transaction(function () use ($result, $from, $to, $apply) {
            $locked = ExamResult::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($result->id);

            if ($locked->status !== $from) {
                throw new DomainRuleException("A result that is {$locked->status->label()} cannot become {$to->label()}.");
            }

            $apply($locked);
            $locked->status = $to;
            $locked->save();
        });

        $result->refresh();
    }

    private function score(mixed $value, int $max, string $label, $subject): int
    {
        if ($value === null || $value === '') {
            $value = 0;
        }

        if (! is_numeric($value) || (int) $value != $value || (int) $value < 0 || (int) $value > $max) {
            throw new DomainRuleException("{$label} marks for {$subject->subject?->code} must be a whole number from 0 to {$max}.");
        }

        return (int) $value;
    }
}
