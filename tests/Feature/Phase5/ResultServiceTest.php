<?php

use App\Enums\EnrollmentStatus;
use App\Enums\ResultOutcome;
use App\Enums\ResultStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ResultService;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class]);
    $this->institute = Institute::factory()->create();
    $this->teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');
    $this->receptionist = InstituteUser::factory()->for($this->institute)->create()->assignRole('receptionist');
    $this->academic = Admin::factory()->create()->assignRole('academic');
    $this->course = Course::factory()->create();
    // Two subjects: 70 theory + 30 practical each, pass at 33.
    $this->cs1 = $this->course->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);
    $this->cs2 = $this->course->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);
    $this->enrollment = Enrollment::create([
        'institute_id' => $this->institute->id, 'student_id' => Student::factory()->for($this->institute)->create()->id,
        'course_id' => $this->course->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'admission_date' => '2026-01-01', 'course_fee' => 100, 'discount' => 0, 'net_fee' => 100, 'status' => EnrollmentStatus::Active,
    ]);
    $this->results = app(ResultService::class);
});

function marks(array $one, array $two): array
{
    return [test()->cs1->id => $one, test()->cs2->id => $two];
}

it('saves marks as a draft result with totals per subject', function () {
    $result = $this->results->saveMarks($this->enrollment, marks(['theory' => 60, 'practical' => 25], ['theory' => 50, 'practical' => 20]), '2026-09-20', $this->teacher);

    expect($result->status)->toBe(ResultStatus::Draft)
        ->and($result->marks)->toHaveCount(2)
        ->and($result->marks->firstWhere('course_subject_id', $this->cs1->id)->total)->toBe(85);
});

it('refuses marks above the maximum, missing subjects, or foreign subject ids', function () {
    $foreign = Course::factory()->create()->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 100, 'pass_marks' => 33]);

    expect(fn () => $this->results->saveMarks($this->enrollment, marks(['theory' => 71, 'practical' => 0], ['theory' => 1, 'practical' => 1]), '2026-09-20', $this->teacher))
        ->toThrow(DomainRuleException::class)
        ->and(fn () => $this->results->saveMarks($this->enrollment, [$this->cs1->id => ['theory' => 1, 'practical' => 1]], '2026-09-20', $this->teacher))
        ->toThrow(DomainRuleException::class)
        ->and(fn () => $this->results->saveMarks($this->enrollment, [...marks(['theory' => 1, 'practical' => 1], ['theory' => 1, 'practical' => 1]), $foreign->id => ['theory' => 1]], '2026-09-20', $this->teacher))
        ->toThrow(DomainRuleException::class);
});

it('refuses staff without enter_marks and other institutes', function () {
    $stranger = InstituteUser::factory()->for(Institute::factory())->create()->assignRole('teacher');
    $m = marks(['theory' => 1, 'practical' => 1], ['theory' => 1, 'practical' => 1]);

    expect(fn () => $this->results->saveMarks($this->enrollment, $m, '2026-09-20', $this->receptionist))->toThrow(DomainRuleException::class)
        ->and(fn () => $this->results->saveMarks($this->enrollment, $m, '2026-09-20', $stranger))->toThrow(DomainRuleException::class);
});

it('refuses marks for enrollments that are not active', function () {
    $this->enrollment->update(['status' => EnrollmentStatus::PendingApproval]);

    expect(fn () => $this->results->saveMarks($this->enrollment, marks(['theory' => 1, 'practical' => 1], ['theory' => 1, 'practical' => 1]), '2026-09-20', $this->teacher))
        ->toThrow(DomainRuleException::class);
});

it('publishes with percentage, grade, pass, and completes the enrollment', function () {
    $result = $this->results->saveMarks($this->enrollment, marks(['theory' => 60, 'practical' => 25], ['theory' => 50, 'practical' => 20]), '2026-09-20', $this->teacher);
    $this->results->submit($result, $this->teacher);

    $this->results->publish($result, $this->academic);

    $result->refresh();
    expect($result->status)->toBe(ResultStatus::Published)
        ->and($result->total_obtained)->toBe(155)
        ->and($result->total_max)->toBe(200)
        ->and($result->percentage)->toBe('77.50')
        ->and($result->grade)->toBe('A')
        ->and($result->result)->toBe(ResultOutcome::Pass)
        ->and($result->published_by_admin_id)->toBe($this->academic->id)
        ->and($this->enrollment->fresh()->status)->toBe(EnrollmentStatus::Completed);
});

it('fails a student who is absent or below pass marks in any subject', function (array $two) {
    $result = $this->results->saveMarks($this->enrollment, marks(['theory' => 70, 'practical' => 30], $two), '2026-09-20', $this->teacher);
    $this->results->submit($result, $this->teacher);
    $this->results->publish($result, $this->academic);

    expect($result->fresh()->result)->toBe(ResultOutcome::Fail);
})->with([
    'absent' => [['absent' => true]],
    'below pass' => [['theory' => 20, 'practical' => 12]],
]);

it('rounds the percentage before choosing the grade', function () {
    $course = Course::factory()->create();
    $cs = $course->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 30000, 'practical_max' => 0, 'pass_marks' => 9900]);
    $this->enrollment->update(['course_id' => $course->id]);
    // 26999/30000 = 89.9967% falls in the 0.01 gap between A (…89.99) and A+ (90…) unless rounded first.
    $result = $this->results->saveMarks($this->enrollment->fresh(), [$cs->id => ['theory' => 26999]], '2026-09-20', $this->teacher);
    $this->results->submit($result, $this->teacher);
    $this->results->publish($result, $this->academic);

    expect($result->fresh()->grade)->toBe('A+');
});

it('returns a submitted result with remarks and allows editing again', function () {
    $result = $this->results->saveMarks($this->enrollment, marks(['theory' => 1, 'practical' => 1], ['theory' => 1, 'practical' => 1]), '2026-09-20', $this->teacher);
    $this->results->submit($result, $this->teacher);

    expect(fn () => $this->results->return($result, ' ', $this->academic))->toThrow(DomainRuleException::class);
    $this->results->return($result, 'Recheck subject 2', $this->academic);

    expect($result->fresh()->status)->toBe(ResultStatus::Returned)
        ->and($result->fresh()->return_remarks)->toBe('Recheck subject 2');

    $this->results->saveMarks($this->enrollment, marks(['theory' => 40, 'practical' => 20], ['theory' => 40, 'practical' => 20]), '2026-09-20', $this->teacher);
    expect($result->fresh()->status)->toBe(ResultStatus::Draft);
});

it('refuses edits after submit or publish, double publish, and publish of a draft', function () {
    $result = $this->results->saveMarks($this->enrollment, marks(['theory' => 50, 'practical' => 20], ['theory' => 50, 'practical' => 20]), '2026-09-20', $this->teacher);

    expect(fn () => $this->results->publish($result, $this->academic))->toThrow(DomainRuleException::class);

    $this->results->submit($result, $this->teacher);
    expect(fn () => $this->results->saveMarks($this->enrollment, marks(['theory' => 1, 'practical' => 1], ['theory' => 1, 'practical' => 1]), '2026-09-20', $this->teacher))
        ->toThrow(DomainRuleException::class);

    $this->results->publish($result, $this->academic);
    expect(fn () => $this->results->publish($result, $this->academic))->toThrow(DomainRuleException::class)
        ->and(ExamResult::count())->toBe(1);
});

it('lets only teachers with submit_result submit', function () {
    $result = $this->results->saveMarks($this->enrollment, marks(['theory' => 1, 'practical' => 1], ['theory' => 1, 'practical' => 1]), '2026-09-20', $this->teacher);

    expect(fn () => $this->results->submit($result, $this->receptionist))->toThrow(DomainRuleException::class);
});
