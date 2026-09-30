<?php

use App\Enums\EnrollmentStatus;
use App\Filament\Student\Pages\MyProfile;
use App\Filament\Student\Resources\MyEnrollments\Pages\ListMyEnrollments;
use App\Filament\Student\Resources\MyEnrollments\Pages\ViewMyEnrollment;
use App\Filament\Student\Resources\MyPayments\Pages\ListMyPayments;
use App\Filament\Student\Widgets\StudentOverviewWidget;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Subject;
use App\Services\FeeService;
use App\Services\ResultService;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class]);
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $this->academic = Admin::factory()->create()->assignRole('academic');
    $this->course = Course::factory()->create(['name' => ['en' => 'Advanced Diploma PQR']]);
    $this->cs = $this->course->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);
    Filament::setCurrentPanel('student');
});

function portalEnrollment(Student $student, string $no, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
{
    return Enrollment::create([
        'institute_id' => $student->institute_id, 'student_id' => $student->id, 'course_id' => test()->course->id,
        'academic_session_id' => AcademicSession::factory()->create()->id, 'enrollment_no' => "KCTI/2026/{$no}",
        'admission_date' => '2026-01-01', 'course_fee' => 1000, 'discount' => 0, 'net_fee' => 1000, 'status' => $status,
    ]);
}

function makeStudent(?string $aadhaar = null): Student
{
    $s = Student::factory()->canLogin()->for(test()->institute)->create();
    $s->personal()->create(['dob' => '2002-02-02', 'gender' => 'female', 'aadhaar' => $aadhaar]);

    return $s;
}

it('lists only my approved enrollments and my receipts', function () {
    $me = makeStudent();
    $mine = portalEnrollment($me, '000601');
    $pending = portalEnrollment($me, '000602', EnrollmentStatus::PendingApproval);
    $other = portalEnrollment(makeStudent(), '000603');
    $myPayment = app(FeeService::class)->collect($mine, '300', 'cash', null, now()->toDateString(), $this->owner);
    $otherPayment = app(FeeService::class)->collect($other, '200', 'cash', null, now()->toDateString(), $this->owner);

    $this->actingAs($me, 'student');
    Livewire::test(ListMyEnrollments::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$pending, $other]);
    Livewire::test(ListMyPayments::class)->assertCanSeeTableRecords([$myPayment])->assertCanNotSeeTableRecords([$otherPayment]);
});

it('shows fees, attendance and only a published result on the enrollment page', function () {
    $me = makeStudent();
    $enrollment = portalEnrollment($me, '000611');
    app(FeeService::class)->collect($enrollment, '400', 'cash', null, now()->toDateString(), $this->owner);
    $results = app(ResultService::class);
    $result = $results->saveMarks($enrollment, [$this->cs->id => ['theory' => 60, 'practical' => 25]], '2026-09-20', $this->owner);

    $this->actingAs($me, 'student');
    $url = ViewMyEnrollment::getUrl(['record' => $enrollment], panel: 'student');
    $this->get($url)->assertOk()->assertSee('Advanced Diploma PQR')->assertSee('400.00')->assertSee('600.00')->assertDontSee('85.00');

    $results->submit($result, $this->owner);
    $results->publish($result, $this->academic);
    $this->get($url)->assertSee('85.00')->assertSee(route('documents.marksheet', $result->fresh()), false);
});

it("gives 404 for another student's enrollment page", function () {
    $other = portalEnrollment(makeStudent(), '000621');

    $this->actingAs(makeStudent(), 'student');
    // the second student needs an approved enrollment for panel access
    portalEnrollment(auth('student')->user(), '000622');

    $this->get(ViewMyEnrollment::getUrl(['record' => $other], panel: 'student'))->assertNotFound();
});

it('shows my profile without the Aadhaar number', function () {
    $me = makeStudent('234123412346');
    portalEnrollment($me, '000631');

    $this->actingAs($me, 'student')->get(MyProfile::getUrl(panel: 'student'))
        ->assertOk()->assertSee($me->registration_no)->assertDontSee('234123412346')->assertDontSee('2346');
});

it('renders the student dashboard', function () {
    $me = makeStudent();
    portalEnrollment($me, '000641');

    $this->actingAs($me, 'student')->get('/student')->assertOk();
    Livewire::test(StudentOverviewWidget::class)->assertSee('1,000.00');
});
