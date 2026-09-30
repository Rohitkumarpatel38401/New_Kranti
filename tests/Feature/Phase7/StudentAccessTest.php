<?php

use App\Enums\EnrollmentStatus;
use App\Enums\InstituteStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Student\Pages\ChangePassword;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Subject;
use App\Services\CertificateService;
use App\Services\FeeService;
use App\Services\ResultService;
use App\Services\StudentAccountService;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class]);
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $this->academic = Admin::factory()->create()->assignRole('academic');
    $this->course = Course::factory()->create();
    $this->cs = $this->course->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);
});

function portalStudent(string $no = '000501', bool $mustChange = false): array
{
    $t = test();
    $student = Student::factory()->canLogin()->for($t->institute)->create(['must_change_password' => $mustChange, 'password' => 'old-pass-123']);
    $student->personal()->create(['dob' => '2002-02-02', 'gender' => 'female']);
    $enrollment = Enrollment::create([
        'institute_id' => $t->institute->id, 'student_id' => $student->id, 'course_id' => $t->course->id,
        'academic_session_id' => AcademicSession::factory()->create()->id, 'enrollment_no' => "KCTI/2026/{$no}",
        'admission_date' => '2026-01-01', 'course_fee' => 1000, 'discount' => 0, 'net_fee' => 1000, 'status' => EnrollmentStatus::Active,
    ]);

    return [$student, $enrollment];
}

/** Receipt, published result and issued certificate for one enrollment. */
function documentsFor(Enrollment $enrollment): array
{
    $t = test();
    $payment = app(FeeService::class)->collect($enrollment, '500', 'cash', null, now()->toDateString(), $t->owner);
    $results = app(ResultService::class);
    $result = $results->saveMarks($enrollment, [$t->cs->id => ['theory' => 60, 'practical' => 25]], '2026-09-20', $t->owner);
    $results->submit($result, $t->owner);
    $results->publish($result, $t->academic);
    $certificate = app(CertificateService::class)->issue($enrollment->fresh(), $t->academic);

    return [$payment, $result->fresh(), $certificate];
}

it('lets a student download their own receipt, marksheet, certificate and ID card', function () {
    [$student, $enrollment] = portalStudent();
    [$payment, $result, $certificate] = documentsFor($enrollment);

    $this->actingAs($student, 'student');
    $this->get(route('receipts.show', $payment))->assertOk();
    $this->get(route('documents.marksheet', $result))->assertOk();
    $this->get(route('documents.certificate', $certificate))->assertOk();
    $this->get(route('documents.id-card', $enrollment))->assertOk();
});

it("refuses another student's documents", function () {
    [, $enrollment] = portalStudent('000501');
    [$payment, $result, $certificate] = documentsFor($enrollment);
    [$other] = portalStudent('000502');

    $this->actingAs($other, 'student');
    $this->get(route('receipts.show', $payment))->assertForbidden();
    $this->get(route('documents.marksheet', $result))->assertForbidden();
    $this->get(route('documents.certificate', $certificate))->assertForbidden();
    $this->get(route('documents.id-card', $enrollment))->assertForbidden();
});

it('refuses a revoked certificate and an admit card for a completed course', function () {
    [$student, $enrollment] = portalStudent();
    [, , $certificate] = documentsFor($enrollment);
    app(CertificateService::class)->revoke($certificate, 'Issued in error', $this->academic);

    $this->actingAs($student, 'student');
    $this->get(route('documents.certificate', $certificate))->assertForbidden();
    $this->get(route('documents.admit-card', $enrollment))->assertNotFound();
});

it('refuses an unpublished result', function () {
    [$student, $enrollment] = portalStudent();
    $result = app(ResultService::class)->saveMarks($enrollment, [$this->cs->id => ['theory' => 60, 'practical' => 25]], '2026-09-20', $this->owner);

    $this->actingAs($student, 'student')->get(route('documents.marksheet', $result))->assertForbidden();
});

it('sends a student who must change their password to the change-password page', function () {
    [$student] = portalStudent(mustChange: true);

    $this->actingAs($student, 'student')->get('/student')->assertRedirect(ChangePassword::getUrl(panel: 'student'));
    $this->actingAs($student, 'student')->get(ChangePassword::getUrl(panel: 'student'))->assertOk();
});

it('refuses Livewire calls from other student pages until the password is changed', function () {
    [$student] = portalStudent();
    $this->actingAs($student, 'student');
    $html = $this->get('/student')->getContent();
    $student->forceFill(['must_change_password' => true])->save();

    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $m);
    $this->withHeaders(['X-Livewire' => 'true'])
        ->postJson(EndpointResolver::updatePath(), [
            '_token' => csrf_token(),
            'components' => [['snapshot' => html_entity_decode($m[1][0]), 'updates' => [], 'calls' => []]],
        ])
        ->assertForbidden();
});

it('changes the password and clears the flag', function () {
    [$student] = portalStudent(mustChange: true);
    Filament::setCurrentPanel('student');
    $this->actingAs($student, 'student');

    Livewire::test(ChangePassword::class)
        ->fillForm(['current_password' => 'old-pass-123', 'password' => 'new-pass-456', 'password_confirmation' => 'new-pass-456'])
        ->call('save')
        ->assertHasNoFormErrors();

    $student->refresh();
    expect($student->must_change_password)->toBeFalse()
        ->and(Hash::check('new-pass-456', $student->password))->toBeTrue();
    $this->get('/student')->assertOk();
});

it('refuses a wrong current password, a reused password and a short one', function () {
    [$student] = portalStudent(mustChange: true);
    $accounts = app(StudentAccountService::class);

    expect(fn () => $accounts->changePassword($student, 'wrong', 'new-pass-456'))->toThrow(DomainRuleException::class)
        ->and(fn () => $accounts->changePassword($student, 'old-pass-123', 'old-pass-123'))->toThrow(DomainRuleException::class)
        ->and(fn () => $accounts->changePassword($student, 'old-pass-123', 'short'))->toThrow(DomainRuleException::class);
    expect($student->fresh()->must_change_password)->toBeTrue();
});

it('lets the change-password form itself work through the real Livewire endpoint while the flag is set', function () {
    [$student] = portalStudent(mustChange: true);
    $this->actingAs($student, 'student');
    $html = $this->get(ChangePassword::getUrl(panel: 'student'))->getContent();

    livewireUpdate($this, $html, ChangePassword::class, [
        'data.current_password' => 'old-pass-123', 'data.password' => 'new-pass-456', 'data.password_confirmation' => 'new-pass-456',
    ], [['path' => '', 'method' => 'save', 'params' => []]]);

    expect($student->fresh()->must_change_password)->toBeFalse();
});

// Phase 7 review, Important 1: document routes must respect the student's account state
it('refuses documents to a student whose login was closed, whose institute is suspended, or who must change the password', function (string $case) {
    [$student, $enrollment] = portalStudent();
    [$payment] = documentsFor($enrollment);

    match ($case) {
        'closed' => $student->forceFill(['can_login' => false])->save(),
        'suspended' => $this->institute->forceFill(['status' => InstituteStatus::Suspended])->save(),
        'must_change' => $student->forceFill(['must_change_password' => true])->save(),
    };

    $this->actingAs($student->fresh(), 'student');
    $this->get(route('receipts.show', $payment))->assertForbidden();
    $this->get(route('documents.id-card', $enrollment))->assertForbidden();
})->with(['closed', 'suspended', 'must_change']);
