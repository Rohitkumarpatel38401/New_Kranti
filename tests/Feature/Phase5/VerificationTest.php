<?php

use App\Enums\EnrollmentStatus;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Subject;
use App\Services\CertificateService;
use App\Services\ResultService;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class]);
    $institute = Institute::factory()->create(['name' => 'Ganga Centre']);
    $teacher = InstituteUser::factory()->for($institute)->create()->assignRole('teacher');
    $admin = Admin::factory()->create()->assignRole('academic');
    $course = Course::factory()->create(['code' => 'CCC']);
    $cs = $course->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 100, 'pass_marks' => 33]);
    $this->student = Student::factory()->for($institute)->create(['first_name' => 'Neha', 'last_name' => 'Gupta', 'phone' => '9811100000', 'email' => 'neha@private.test']);
    $this->student->personal()->create(['dob' => '2003-07-14', 'gender' => 'female', 'aadhaar' => '345678901234']);
    $this->enrollment = Enrollment::create([
        'institute_id' => $institute->id, 'student_id' => $this->student->id, 'course_id' => $course->id,
        'academic_session_id' => AcademicSession::factory()->create()->id, 'enrollment_no' => 'KCTI/2026/000042',
        'admission_date' => '2026-01-01', 'course_fee' => 1, 'discount' => 0, 'net_fee' => 1, 'status' => EnrollmentStatus::Active,
    ]);
    $results = app(ResultService::class);
    $result = $results->saveMarks($this->enrollment, [$cs->id => ['theory' => 81]], '2026-09-01', $teacher);
    $results->submit($result, $teacher);
    $results->publish($result, $admin);
    $this->certificate = app(CertificateService::class)->issue($this->enrollment, $admin);
    $this->admin = $admin;
});

it('verifies by QR token without leaking private data', function () {
    $this->get(route('site.verify', $this->certificate->verification_token))
        ->assertOk()
        ->assertSee('Genuine certificate')->assertSee('Neha Gupta')->assertSee('CCC')->assertSee('Ganga Centre')
        ->assertDontSee('9811100000')->assertDontSee('neha@private.test')->assertDontSee('345678901234');
});

it('shows the lookup form', function () {
    $this->get(route('site.students.verification'))->assertOk()->assertSee('Certificate number');
});

it('finds a certificate by its number', function () {
    $this->post(route('site.students.verification.lookup'), ['certificate_no' => ' kcti/c/'.now()->year.'/000001 '])
        ->assertOk()->assertSee('Genuine certificate')->assertSee('Neha Gupta');
});

it('finds a certificate by enrollment number and date of birth', function () {
    $this->post(route('site.students.verification.lookup'), ['enrollment_no' => 'KCTI/2026/000042', 'dob' => '2003-07-14'])
        ->assertOk()->assertSee('Neha Gupta');
});

it('does not match a wrong date of birth', function () {
    $this->post(route('site.students.verification.lookup'), ['enrollment_no' => 'KCTI/2026/000042', 'dob' => '2003-07-15'])
        ->assertOk()->assertSee('No certificate found')->assertDontSee('Neha Gupta');
});

it('requires one of the two ways to search', function () {
    $this->post(route('site.students.verification.lookup'), [])->assertSessionHasErrors();
});

it('shows a revoked certificate as revoked', function () {
    app(CertificateService::class)->revoke($this->certificate, 'Duplicate issue', $this->admin);

    $this->get(route('site.verify', $this->certificate->verification_token))
        ->assertOk()->assertSee('revoked')->assertDontSee('Duplicate issue');
});

it('throttles repeated lookups', function () {
    foreach (range(1, 10) as $i) {
        $this->post(route('site.students.verification.lookup'), ['certificate_no' => "NOPE-{$i}"]);
    }

    $this->post(route('site.students.verification.lookup'), ['certificate_no' => 'NOPE-11'])->assertStatus(429);
});
