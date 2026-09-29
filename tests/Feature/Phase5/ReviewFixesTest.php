<?php

use App\Enums\EnrollmentStatus;
use App\Enums\ResultStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeScale;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Subject;
use App\Services\CertificateService;
use App\Services\EnrollmentService;
use App\Services\ResultService;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class]);
    $this->institute = Institute::factory()->create();
    $this->teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $this->academic = Admin::factory()->create()->assignRole('academic');
    $this->course = Course::factory()->create();
    $this->cs = $this->course->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);
    $student = Student::factory()->for($this->institute)->create();
    $student->personal()->create(['dob' => '2001-01-01', 'gender' => 'male']);
    $this->enrollment = Enrollment::create([
        'institute_id' => $this->institute->id, 'student_id' => $student->id, 'course_id' => $this->course->id,
        'academic_session_id' => AcademicSession::factory()->create()->id, 'enrollment_no' => 'KCTI/2026/000777',
        'admission_date' => '2026-01-01', 'course_fee' => 1, 'discount' => 0, 'net_fee' => 1, 'status' => EnrollmentStatus::Active,
    ]);
    $this->results = app(ResultService::class);
});

function submitted(array $marks = ['theory' => 60, 'practical' => 25])
{
    $r = test()->results->saveMarks(test()->enrollment, [test()->cs->id => $marks], '2026-09-20', test()->teacher);
    test()->results->submit($r, test()->teacher);

    return $r->fresh();
}

// Important 1
it('does not publish, and leaves no half-published result, when the enrollment was dropped after submit', function () {
    $result = submitted();
    app(EnrollmentService::class)->drop($this->enrollment, 'Left town', $this->owner);

    expect(fn () => $this->results->publish($result, $this->academic))->toThrow(DomainRuleException::class);
    expect($result->fresh()->status)->toBe(ResultStatus::Submitted);
});

it('refuses a certificate unless the enrollment is completed', function () {
    $result = submitted();
    $this->results->publish($result, $this->academic);
    $this->enrollment->forceFill(['status' => EnrollmentStatus::Dropped])->save();

    expect(fn () => app(CertificateService::class)->issue($this->enrollment->fresh(), $this->academic))->toThrow(DomainRuleException::class);
});

// Important 2
it('refuses to submit blank marks for a subject that is not absent', function () {
    $r = $this->results->saveMarks($this->enrollment, [$this->cs->id => ['theory' => null, 'practical' => '']], '2026-09-20', $this->teacher);

    expect(fn () => $this->results->submit($r, $this->teacher))->toThrow(DomainRuleException::class, 'blank');
});

// Re-graded: subjects added after marks
it('refuses to publish when the course gained a subject after marks were entered', function () {
    $result = submitted();
    $this->course->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 100, 'pass_marks' => 33]);

    expect(fn () => $this->results->publish($result, $this->academic))->toThrow(DomainRuleException::class, 'subjects');
});

// Re-graded: grade scale gap
it('refuses to publish when no grade matches', function () {
    GradeScale::query()->delete();
    $result = submitted();

    expect(fn () => $this->results->publish($result, $this->academic))->toThrow(DomainRuleException::class, 'grade');
    expect($result->fresh()->status)->toBe(ResultStatus::Submitted);
});

// Re-graded: public data trimmed to spec
it('shows only spec fields on public verification and a generic revoked message', function () {
    $this->results->publish(submitted(), $this->academic);
    $certificate = app(CertificateService::class)->issue($this->enrollment->fresh(), $this->academic);
    app(CertificateService::class)->revoke($certificate, 'Internal: fee fraud case #12', $this->academic);

    $this->get(route('site.verify', $certificate->verification_token))
        ->assertOk()
        ->assertSee('revoked')
        ->assertDontSee('fee fraud')
        ->assertDontSee('KCTI/2026/000777')
        ->assertDontSee('85.00');
});

// Re-graded: QR host
it('builds the verification URL from APP_URL, not the request host', function () {
    config(['app.url' => 'https://kcti.example']);
    URL::forceRootUrl('http://10.0.0.5:8000');
    $this->results->publish(submitted(), $this->academic);
    $certificate = app(CertificateService::class)->issue($this->enrollment->fresh(), $this->academic);

    expect($certificate->verificationUrl())->toStartWith('https://kcti.example/verify/');
});
