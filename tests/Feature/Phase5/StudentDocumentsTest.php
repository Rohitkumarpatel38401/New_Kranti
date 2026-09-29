<?php

use App\Enums\EnrollmentStatus;
use App\Filament\Institute\Resources\Certificates\Pages\ListCertificates;
use App\Models\AcademicSession;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
});

function docEnrollment(Institute $institute, EnrollmentStatus $status): Enrollment
{
    $student = Student::factory()->for($institute)->create(['first_name' => 'Sonu']);
    $student->personal()->create(['dob' => '2004-04-04', 'gender' => 'male']);

    return Enrollment::create([
        'institute_id' => $institute->id, 'student_id' => $student->id, 'course_id' => Course::factory()->create()->id,
        'academic_session_id' => AcademicSession::factory()->create()->id, 'enrollment_no' => 'KCTI/2026/'.fake()->unique()->numerify('######'),
        'admission_date' => '2026-01-01', 'course_fee' => 1, 'discount' => 0, 'net_fee' => 1, 'status' => $status,
    ]);
}

it('renders the ID card for active and completed enrollments', function (EnrollmentStatus $status) {
    $enrollment = docEnrollment($this->institute, $status);

    $this->actingAs($this->owner, 'institute')->get(route('documents.id-card', $enrollment))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
})->with([EnrollmentStatus::Active, EnrollmentStatus::Completed]);

it('renders the admit card only for active enrollments', function () {
    $active = docEnrollment($this->institute, EnrollmentStatus::Active);
    $completed = docEnrollment($this->institute, EnrollmentStatus::Completed);

    $this->actingAs($this->owner, 'institute')->get(route('documents.admit-card', $active))->assertOk();
    $this->actingAs($this->owner, 'institute')->get(route('documents.admit-card', $completed))->assertNotFound();
});

it('refuses student cards before approval', function () {
    $draft = docEnrollment($this->institute, EnrollmentStatus::PendingApproval);

    $this->actingAs($this->owner, 'institute')->get(route('documents.id-card', $draft))->assertNotFound();
});

it('forbids another institute from student cards', function () {
    $enrollment = docEnrollment($this->institute, EnrollmentStatus::Active);
    $stranger = InstituteUser::factory()->owner()->for(Institute::factory())->create()->assignRole('owner');

    $this->actingAs($stranger, 'institute')->get(route('documents.id-card', $enrollment))->assertForbidden();
});

it('shows the institute its own certificates only', function () {
    $mine = Certificate::create(['institute_id' => $this->institute->id, 'enrollment_id' => docEnrollment($this->institute, EnrollmentStatus::Completed)->id,
        'certificate_no' => 'KCTI/C/2026/900001', 'verification_token' => (string) Str::ulid(), 'issued_on' => now()]);
    $other = Institute::factory()->create();
    $theirs = Certificate::create(['institute_id' => $other->id, 'enrollment_id' => docEnrollment($other, EnrollmentStatus::Completed)->id,
        'certificate_no' => 'KCTI/C/2026/900002', 'verification_token' => (string) Str::ulid(), 'issued_on' => now()]);
    $this->actingAs($this->owner, 'institute');
    app(CurrentInstitute::class)->set($this->institute->id);
    Filament::setCurrentPanel('institute');

    Livewire::test(ListCertificates::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs])
        ->assertTableActionDoesNotExist('revoke');
});
