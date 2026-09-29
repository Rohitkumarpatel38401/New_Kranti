<?php

use App\Enums\CertificateStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Certificates\Pages\ListCertificates;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Subject;
use App\Services\CertificateService;
use App\Services\ResultService;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-09-30 10:00');
    $this->seed([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class]);
    $this->institute = Institute::factory()->create();
    $this->teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');
    $this->academic = Admin::factory()->create()->assignRole('academic');
    $this->course = Course::factory()->create(['code' => 'DCA']);
    $this->cs = $this->course->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);
    $this->certificates = app(CertificateService::class);
});

function publishedResult(int $theory, int $practical, ?Institute $institute = null): ExamResult
{
    $institute ??= test()->institute;
    $teacher = $institute->is(test()->institute) ? test()->teacher : InstituteUser::factory()->for($institute)->create()->assignRole('teacher');
    $student = Student::factory()->for($institute)->create(['first_name' => 'Kavita', 'last_name' => 'Rai']);
    $student->personal()->create(['dob' => '2002-02-02', 'gender' => 'female']);
    $student->guardians()->create(['relation' => 'father', 'name' => 'Mohan Rai', 'is_primary' => true]);
    $enrollment = Enrollment::create([
        'institute_id' => $institute->id, 'student_id' => $student->id, 'course_id' => test()->course->id,
        'academic_session_id' => AcademicSession::factory()->create()->id, 'enrollment_no' => 'KCTI/2026/'.fake()->unique()->numerify('######'),
        'admission_date' => '2026-01-01', 'course_fee' => 100, 'discount' => 0, 'net_fee' => 100, 'status' => EnrollmentStatus::Active,
    ]);
    $service = app(ResultService::class);
    $result = $service->saveMarks($enrollment, [test()->cs->id => ['theory' => $theory, 'practical' => $practical]], '2026-09-20', $teacher);
    $service->submit($result, $teacher);
    $service->publish($result, test()->academic);

    return $result->fresh();
}

it('issues a numbered certificate with a verification token for a passed result', function () {
    $result = publishedResult(60, 25);

    $certificate = $this->certificates->issue($result->enrollment, $this->academic);

    expect($certificate->certificate_no)->toBe('KCTI/C/2026/000001')
        ->and($certificate->verification_token)->toHaveLength(26)
        ->and($certificate->status)->toBe(CertificateStatus::Issued)
        ->and($certificate->issued_by_admin_id)->toBe($this->academic->id)
        ->and($certificate->institute_id)->toBe($this->institute->id);
});

it('refuses a certificate for an unpublished result', function () {
    $student = Student::factory()->for($this->institute)->create();
    $enrollment = Enrollment::create(['institute_id' => $this->institute->id, 'student_id' => $student->id, 'course_id' => $this->course->id,
        'academic_session_id' => AcademicSession::factory()->create()->id, 'admission_date' => '2026-01-01', 'course_fee' => 1, 'discount' => 0, 'net_fee' => 1,
        'status' => EnrollmentStatus::Active]);

    expect(fn () => $this->certificates->issue($enrollment, $this->academic))->toThrow(DomainRuleException::class);
});

it('refuses a second certificate and a failed result', function () {
    $passed = publishedResult(60, 25);
    $failed = publishedResult(10, 5);
    $this->certificates->issue($passed->enrollment, $this->academic);

    expect(fn () => $this->certificates->issue($passed->enrollment, $this->academic))->toThrow(DomainRuleException::class)
        ->and(fn () => $this->certificates->issue($failed->enrollment, $this->academic))->toThrow(DomainRuleException::class)
        ->and(Certificate::count())->toBe(1);
});

it('revokes with a reason, once', function () {
    $certificate = $this->certificates->issue(publishedResult(60, 25)->enrollment, $this->academic);

    expect(fn () => $this->certificates->revoke($certificate, ' ', $this->academic))->toThrow(DomainRuleException::class);
    $this->certificates->revoke($certificate, 'Issued in error', $this->academic);

    expect($certificate->fresh()->status)->toBe(CertificateStatus::Revoked)
        ->and($certificate->fresh()->revoked_reason)->toBe('Issued in error')
        ->and(fn () => $this->certificates->revoke($certificate, 'again', $this->academic))->toThrow(DomainRuleException::class);
});

it('renders the certificate with student, father, course, grade, number, and QR', function () {
    $certificate = $this->certificates->issue(publishedResult(60, 25)->enrollment, $this->academic);

    $html = view('pdf.certificate', app(CertificateService::class)->viewData($certificate))->render();

    expect($html)->toContain('Kavita Rai')->toContain('Mohan Rai')->toContain('DCA')->toContain('KCTI/C/2026/000001')
        ->toContain('data:image/png;base64,')->not->toContain('REVOKED');
});

it('marks a revoked certificate in the PDF', function () {
    $certificate = $this->certificates->issue(publishedResult(60, 25)->enrollment, $this->academic);
    $this->certificates->revoke($certificate, 'Error', $this->academic);

    expect(view('pdf.certificate', app(CertificateService::class)->viewData($certificate->fresh()))->render())->toContain('REVOKED');
});

it('downloads the certificate and marksheet for the institute and HQ only', function () {
    $result = publishedResult(60, 25);
    $certificate = $this->certificates->issue($result->enrollment, $this->academic);
    $owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $stranger = InstituteUser::factory()->owner()->for(Institute::factory())->create()->assignRole('owner');

    $this->actingAs($owner, 'institute')->get(route('documents.certificate', $certificate))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($owner, 'institute')->get(route('documents.marksheet', $result))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('forbids another institute from certificate downloads', function () {
    $certificate = $this->certificates->issue(publishedResult(60, 25)->enrollment, $this->academic);
    $stranger = InstituteUser::factory()->owner()->for(Institute::factory())->create()->assignRole('owner');

    $this->actingAs($stranger, 'institute')->get(route('documents.certificate', $certificate))->assertForbidden();
});

it('issues from the HQ results list and revokes from the certificates list', function () {
    $result = publishedResult(60, 25);
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->academic, 'admin');

    Livewire::test(ListExamResults::class)->callTableAction('issueCertificate', $result)->assertHasNoTableActionErrors();
    $certificate = Certificate::sole();

    Livewire::test(ListCertificates::class)
        ->assertCanSeeTableRecords([$certificate])
        ->callTableAction('revoke', $certificate, data: ['reason' => 'Wrong name'])
        ->assertHasNoTableActionErrors();

    expect($certificate->fresh()->status)->toBe(CertificateStatus::Revoked);
});
