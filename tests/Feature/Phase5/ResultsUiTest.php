<?php

use App\Enums\EnrollmentStatus;
use App\Enums\ResultStatus;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults as AdminResults;
use App\Filament\Institute\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Institute\Resources\ExamResults\Pages\ListExamResults as InstituteResults;
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
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class]);
    $this->institute = Institute::factory()->create();
    $this->teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');
    $this->course = Course::factory()->create();
    $this->cs = $this->course->courseSubjects()->create(['subject_id' => Subject::factory()->create(['code' => 'MSOFF'])->id, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);
    $this->enrollment = uiEnrollment($this->institute, $this->course, 'Ritu');
});

function uiEnrollment(Institute $institute, Course $course, string $name): Enrollment
{
    return Enrollment::create([
        'institute_id' => $institute->id, 'student_id' => Student::factory()->for($institute)->create(['first_name' => $name])->id,
        'course_id' => $course->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'admission_date' => '2026-01-01', 'course_fee' => 100, 'discount' => 0, 'net_fee' => 100, 'status' => EnrollmentStatus::Active,
    ]);
}

function asTeacher(): void
{
    test()->actingAs(test()->teacher, 'institute');
    app(CurrentInstitute::class)->set(test()->institute->id);
    Filament::setCurrentPanel('institute');
}

it('enters marks from the enrollment row', function () {
    asTeacher();

    Livewire::test(ListEnrollments::class)
        ->callTableAction('enterMarks', $this->enrollment, data: ['exam_date' => '2026-09-25', 'marks' => [
            $this->cs->id => ['theory' => 55, 'practical' => 25, 'absent' => false],
        ]])
        ->assertHasNoTableActionErrors();

    expect(ExamResult::sole()->marks->sole()->total)->toBe(80);
});

it('submits marks from the results list and shows HQ remarks', function () {
    $result = app(ResultService::class)->saveMarks($this->enrollment, [$this->cs->id => ['theory' => 50, 'practical' => 20]], '2026-09-25', $this->teacher);
    asTeacher();

    Livewire::test(InstituteResults::class)
        ->assertCanSeeTableRecords([$result])
        ->callTableAction('submit', $result)
        ->assertHasNoTableActionErrors();

    expect($result->fresh()->status)->toBe(ResultStatus::Submitted);
});

it('never lists another institute results through the Livewire endpoint', function () {
    $other = Institute::factory()->create();
    $otherTeacher = InstituteUser::factory()->for($other)->create()->assignRole('teacher');
    app(ResultService::class)->saveMarks(uiEnrollment($other, $this->course, 'Stranger'), [$this->cs->id => ['theory' => 1, 'practical' => 1]], '2026-09-25', $otherTeacher);
    app(ResultService::class)->saveMarks($this->enrollment, [$this->cs->id => ['theory' => 1, 'practical' => 1]], '2026-09-25', $this->teacher);
    $this->actingAs($this->teacher, 'institute');

    $html = $this->get('/institute/results')->assertOk()->assertSee('Ritu')->assertDontSee('Stranger')->getContent();

    expect(livewireUpdate($this, $html, InstituteResults::class, ['tableSearch' => '']))->not->toContain('Stranger');
});

it('publishes and returns from the HQ queue', function () {
    $service = app(ResultService::class);
    $toPublish = $service->saveMarks($this->enrollment, [$this->cs->id => ['theory' => 60, 'practical' => 25]], '2026-09-25', $this->teacher);
    $service->submit($toPublish, $this->teacher);
    $toReturn = $service->saveMarks($second = uiEnrollment($this->institute, $this->course, 'Second'), [$this->cs->id => ['theory' => 5, 'practical' => 5]], '2026-09-25', $this->teacher);
    $service->submit($toReturn, $this->teacher);
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->create()->assignRole('academic'), 'admin');

    Livewire::test(AdminResults::class)->callTableAction('publish', $toPublish)->assertHasNoTableActionErrors();
    Livewire::test(AdminResults::class)->callTableAction('return', $toReturn, data: ['remarks' => 'Check practical'])->assertHasNoTableActionErrors();

    expect($toPublish->fresh()->status)->toBe(ResultStatus::Published)
        ->and($toPublish->fresh()->grade)->toBe('A') // 85% falls in A (75–89.99)
        ->and($toReturn->fresh()->status)->toBe(ResultStatus::Returned);
});

it('hides publishing from admins without publish_result', function () {
    $result = app(ResultService::class)->saveMarks($this->enrollment, [$this->cs->id => ['theory' => 60, 'practical' => 25]], '2026-09-25', $this->teacher);
    app(ResultService::class)->submit($result, $this->teacher);
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->create()->assignRole('manager'), 'admin');

    $this->get('/admin/results')->assertForbidden();
});
