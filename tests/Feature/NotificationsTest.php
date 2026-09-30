<?php

use App\Enums\EnquiryType;
use App\Enums\EnrollmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Subject;
use App\Services\CertificateService;
use App\Services\EnquiryService;
use App\Services\EnrollmentService;
use App\Services\ResultService;
use App\Services\WalletRechargeService;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class]);
    $this->main = Institute::factory()->main()->create();
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $this->manager = Admin::factory()->create()->assignRole('manager');
    $this->support = Admin::factory()->create()->assignRole('support');
    $this->academic = Admin::factory()->create()->assignRole('academic');
    $this->accounts = Admin::factory()->create()->assignRole('accounts');
});

function titles($notifiable): array
{
    return $notifiable->fresh()->notifications->map(fn ($n) => $n->data['title'] ?? null)->all();
}

it('shows a website link and the notification bell in every panel', function (string $panel) {
    expect(Filament::getPanel($panel)->hasDatabaseNotifications())->toBeTrue();
})->with(['admin', 'institute', 'student']);

it('renders the Visit website button next to the profile menu', function () {
    $this->actingAs($this->manager, 'admin')->get('/admin')->assertOk()->assertSee('Visit website')->assertSee('href="'.url('/').'"', false);
});

it('tells only enquiry handlers about a new website enquiry', function () {
    app(EnquiryService::class)->submit(['type' => EnquiryType::Admission, 'name' => 'Asha', 'phone' => '9876543210']);

    expect(titles($this->support))->toContain('New admission enquiry from Asha')
        ->and(titles($this->academic))->toBe([]);
});

it('tells HQ about a submitted enrollment and the institute about the decision', function () {
    $course = Course::factory()->create(['hq_registration_fee' => 0]);
    $this->institute->courses()->attach($course->id, ['is_active' => true]);
    $student = Student::factory()->for($this->institute)->create(['first_name' => 'Ravi', 'last_name' => 'K']);
    $enrollments = app(EnrollmentService::class);
    $enrollment = $enrollments->createDraft($student, $course, null, AcademicSession::current(), '1000', '0', $this->owner);

    $enrollments->submit($enrollment, $this->owner);
    expect(collect(titles($this->manager))->first())->toContain('Enrollment awaiting approval');

    $enrollments->approve($enrollment->fresh(), $this->manager);
    expect(collect(titles($this->owner))->first())->toContain('Enrollment approved');
});

it('tells the institute when HQ approves a wallet recharge', function () {
    $request = app(WalletRechargeService::class)->request(['amount' => '500', 'payment_mode' => 'upi', 'utr_reference' => 'UTR1', 'paid_on' => now()->toDateString()], $this->owner);
    expect(collect(titles($this->accounts))->first())->toContain('Wallet recharge to verify');

    app(WalletRechargeService::class)->approve($request, $this->accounts);
    expect(collect(titles($this->owner))->first())->toContain('Wallet recharge approved');
});

it('tells the student when the result is published and the certificate issued', function () {
    $course = Course::factory()->create();
    $cs = $course->courseSubjects()->create(['subject_id' => Subject::factory()->create()->id, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);
    $student = Student::factory()->for($this->institute)->create();
    $student->personal()->create(['dob' => '2002-02-02', 'gender' => 'male']);
    $enrollment = Enrollment::create([
        'institute_id' => $this->institute->id, 'student_id' => $student->id, 'course_id' => $course->id,
        'academic_session_id' => AcademicSession::current()->id, 'enrollment_no' => 'KCTI/2026/000901',
        'admission_date' => '2026-01-01', 'course_fee' => 1, 'discount' => 0, 'net_fee' => 1, 'status' => EnrollmentStatus::Active,
    ]);
    $results = app(ResultService::class);
    $result = $results->saveMarks($enrollment, [$cs->id => ['theory' => 60, 'practical' => 25]], '2026-09-20', $this->owner);
    $results->submit($result, $this->owner);
    expect(collect(titles($this->academic))->first())->toContain('Result to review');

    $results->publish($result, $this->academic);
    app(CertificateService::class)->issue($enrollment->fresh(), $this->academic);

    expect(titles($student))->toContain('Your result is published')->toContain('Your certificate is ready');
});

it('sends nothing when the change is rolled back', function () {
    $course = Course::factory()->create(['hq_registration_fee' => 900]);
    $this->institute->courses()->attach($course->id, ['is_active' => true]);
    $student = Student::factory()->for($this->institute)->create();
    $enrollment = app(EnrollmentService::class)->createDraft($student, $course, null, AcademicSession::current(), '1000', '0', $this->owner);

    // Wallet is empty: submit fails and must not notify HQ.
    expect(fn () => app(EnrollmentService::class)->submit($enrollment, $this->owner))->toThrow(DomainRuleException::class);
    expect(titles($this->manager))->toBe([]);
});
